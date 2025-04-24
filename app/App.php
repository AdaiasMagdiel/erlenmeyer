<?php

namespace AdaiasMagdiel\Erlenmeyer;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Exception;

/**
 * Main application class for handling HTTP routing, assets, middlewares, and exception handling.
 *
 * This class serves as the core of the application, managing routes, serving static assets,
 * applying middlewares, and handling exceptions. It supports dynamic routes, custom error
 * handling, and is designed for extensibility in projects like a landing page generator.
 */
class App
{
	/** @var Assets Asset manager instance for serving static files. */
	private Assets $assets;

	/** @var Closure Custom handler for 404 errors. */
	private Closure $_404;

	/** @var array List of global middlewares applied to all routes. */
	private array $globalMiddlewares = [];

	/** @var array Map of exception classes to their respective handlers. */
	private array $exceptionHandlers = [];

	/** @var string Path to the log directory. */
	private ?string $logDir;
	private string $logFile;

	/** @var array Storage for routes, indexed by HTTP method (e.g., ['GET' => ['/route' => handler]]). */
	private array $routes = [];

	/** @var string Regex pattern for matching route parameters (e.g., /[param]). */
	private string $routePattern = '/\/\[[a-zA-Z0-9\.\-_]+\]/';

	/** @var string Regex replacement for route parameters (e.g., ([a-zA-Z0-9\.\-_]+)). */
	private string $paramPattern = '/([a-zA-Z0-9\.\-_]+)';

	/** @var int Maximum log file size in bytes (3MB). */
	private const MAX_LOG_SIZE = 3145728; // 3MB

	/** @var int Maximum number of rotated log files to keep. */
	private const MAX_LOG_FILES = 5;

	/**
	 * Constructs the application instance.
	 *
	 * Initializes the asset manager, default 404 handler, default exception handler,
	 * and fallback route for handling asset requests or 404 errors.
	 *
	 * @param string $assetsDir Directory for static assets (default: "/public"). Set to null to disable assets.
	 * @param string $assetsRoute Base route for serving assets (default: "/assets").
	 * @param bool $autoServeAssets Whether to automatically serve assets (default: true).
	 * @throws InvalidArgumentException If the assets directory is invalid or inaccessible, or if the assets route is malformed.
	 */
	public function __construct(
		private string $assetsDir = "/public",
		private string $assetsRoute = "/assets",
		bool $autoServeAssets = true,
		?string $logDir = ""
	) {
		$this->logDir = empty($logDir) ? null : $logDir;

		// Ensure logs directory exists
		if (!is_dir($this->logDir))
			mkdir($this->logDir, 0755, true);

		if (!is_null($this->logDir))
			$this->logFile = $this->logDir . "/info.log";

		// Log application startup
		$this->logMessage('INFO', 'Application initialized with assetsDir: ' . $assetsDir . ', assetsRoute: ' . $assetsRoute);

		if ($autoServeAssets) {
			// Resolve and validate the assets directory path
			$this->assetsDir = realpath($assetsDir);
			if ($this->assetsDir === false || !is_dir($this->assetsDir) || !is_readable($this->assetsDir)) {
				$this->logMessage('ERROR', "Invalid or inaccessible assets directory: $assetsDir");
				throw new InvalidArgumentException("Invalid or inaccessible assets directory: $assetsDir");
			}

			// Validate the assets route format (allows subdirectories)
			if (!preg_match('/^\/[a-zA-Z0-9_-]+(\/[a-zA-Z0-9_-]+)*\/?$/', $assetsRoute)) {
				$this->logMessage('ERROR', "Invalid assets route: $assetsRoute");
				throw new InvalidArgumentException("Invalid assets route: $assetsRoute");
			}

			// Initialize asset manager
			$this->assets = new Assets($assetsDir, $assetsRoute);
			$this->logMessage('INFO', 'Asset manager initialized for route: ' . $assetsRoute);
		}

		// Set default 404 handler
		$this->set404Handler(function (Request $req, Response $res, $params): void {
			$res
				->setStatusCode(404)
				->withHtml("<h1>404 Not Found</h1><p>Requested URI: {$req->getUri()}</p>")
				->send();
		});

		// Set default handler for generic exceptions
		$this->setExceptionHandler(Exception::class, function (Request $req, Response $res, Exception $e) {
			$res
				->setStatusCode(500)
				->withHtml("<h1>500 Internal Server Error</h1><p>Error: {$e->getMessage()}</p>")
				->send();
			$this->logError($e, $req);
		});

		// Initialize routes with 404 and fallback handlers
		$this->routes['404'] = $this->_404;
		$this->routes['fallback'] = function () use ($autoServeAssets) {
			// Serve assets if the request matches the assets route
			if ($autoServeAssets && $this->assets->isAssetRequest()) {
				$this->logMessage('INFO', 'Serving asset: ' . $_SERVER['REQUEST_URI']);
				$this->assets->serveAsset();
				return;
			}
			// Apply global middlewares and execute 404 handler
			$this->logMessage('WARNING', 'No route matched, executing 404 handler for URI: ' . $_SERVER['REQUEST_URI']);
			$handler = $this->applyMiddlewares($this->_404, $this->globalMiddlewares);
			$params = new stdClass();
			$handler(new Request(), new Response(), $params);
		};
	}

	/**
	 * Registers a handler for a specific exception type.
	 *
	 * @param string $exceptionClass Fully qualified name of the exception class (e.g., Exception::class).
	 * @param callable $handler Callable that accepts Request, Response, and the Exception.
	 * @return void
	 * @throws InvalidArgumentException If the exception class does not exist or is not a subclass of Exception.
	 */
	public function setExceptionHandler(string $exceptionClass, callable $handler): void
	{
		// Validate that the class exists and is either Exception or a subclass
		if (!class_exists($exceptionClass) || (
			$exceptionClass !== Exception::class &&
			!is_subclass_of($exceptionClass, Exception::class)
		)) {
			$this->logMessage('ERROR', "Invalid exception class: $exceptionClass");
			throw new InvalidArgumentException("Invalid exception class: $exceptionClass");
		}
		// Store the handler as a Closure
		$this->exceptionHandlers[$exceptionClass] = Closure::fromCallable($handler);
		$this->logMessage('INFO', "Exception handler registered for class: $exceptionClass");
	}

	/**
	 * Retrieves the handler for a thrown exception, traversing the class hierarchy.
	 *
	 * @param Exception $e The thrown exception.
	 * @return Closure|null The corresponding handler or null if none is found.
	 */
	public function getExceptionHandler(Exception $e): ?Closure
	{
		// Check for a direct handler match
		$class = get_class($e);
		while ($class && isset($this->exceptionHandlers[$class])) {
			return $this->exceptionHandlers[$class];
		}

		// Traverse parent classes for a handler
		$parent = get_parent_class($class);
		while ($parent) {
			if (isset($this->exceptionHandlers[$parent])) {
				return $this->exceptionHandlers[$parent];
			}
			$parent = get_parent_class($parent);
		}

		// Fallback to generic Exception handler
		return $this->exceptionHandlers[Exception::class] ?? null;
	}

	/**
	 * Registers a route for a specific HTTP method.
	 *
	 * @param string $method HTTP method (e.g., GET, POST).
	 * @param string $route Route pattern (e.g., /users/[id]).
	 * @param callable $action Action to execute when the route is matched.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 * @throws InvalidArgumentException If the HTTP method is invalid.
	 */
	public function route(string $method, string $route, callable $action, array $middlewares = []): void
	{
		// Normalize and validate HTTP method
		$method = strtoupper(trim($method));
		if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'], true)) {
			$this->logMessage('ERROR', "Invalid HTTP method: $method for route: $route");
			throw new InvalidArgumentException("Invalid HTTP method: $method");
		}

		// Convert route to regex pattern
		$formattedRoute = $this->parseRoute($route);

		// Combine global and route-specific middlewares
		$allMiddlewares = array_merge($this->globalMiddlewares, $middlewares);
		$handler = $this->applyMiddlewares($action, $allMiddlewares);

		// Initialize method-specific routes array if not set
		if (!isset($this->routes[$method])) {
			$this->routes[$method] = [];
		}

		// Extract parameter names from the route
		preg_match_all('/\[([a-zA-Z0-9\.\-_]+)\]/', $route, $matches);
		$paramNames = $matches[1] ?? [];

		// Store route data
		$this->routes[$method][$formattedRoute] = [
			'handler' => $handler,
			'paramNames' => $paramNames
		];
		$this->logMessage('INFO', "Route registered: $method $route");
	}

	/**
	 * Registers a GET route.
	 *
	 * @param string $route Route pattern.
	 * @param callable $action Action to execute.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 */
	public function get(string $route, callable $action, array $middlewares = []): void
	{
		$this->route('GET', $route, $action, $middlewares);
	}

	/**
	 * Registers a POST route.
	 *
	 * @param string $route Route pattern.
	 * @param callable $action Action to execute.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 */
	public function post(string $route, callable $action, array $middlewares = []): void
	{
		$this->route('POST', $route, $action, $middlewares);
	}

	/**
	 * Registers a redirect from one route to another.
	 *
	 * @param string $from Source route.
	 * @param string $to Destination route.
	 * @param bool $permanent Whether the redirect is permanent (301) or temporary (302).
	 * @return void
	 */
	public function redirect(string $from, string $to, bool $permanent = false): void
	{
		// Initialize redirects array if not set
		if (!isset($this->routes['redirects'])) {
			$this->routes['redirects'] = [];
		}
		// Store redirect configuration
		$this->routes['redirects'][] = [
			'from' => $from,
			'to' => $to,
			'permanent' => $permanent
		];
		$this->logMessage('INFO', "Redirect registered: $from to $to (permanent: " . ($permanent ? 'true' : 'false') . ")");
	}

	/**
	 * Sets the custom 404 error handler.
	 *
	 * @param callable $action Action to execute for 404 errors.
	 * @return void
	 */
	public function set404Handler(callable $action): void
	{
		// Store and register the 404 handler
		$this->_404 = Closure::fromCallable($action);
		$this->routes['404'] = $this->_404;
		$this->logMessage('INFO', 'Custom 404 handler set');
	}

	/**
	 * Adds a global middleware to be applied to all routes.
	 *
	 * @param callable $middleware Middleware to apply.
	 * @return void
	 */
	public function addMiddleware(callable $middleware): void
	{
		// Append middleware to global list
		$this->globalMiddlewares[] = $middleware;
		$this->logMessage('INFO', 'Global middleware added');
	}

	/**
	 * Executes the application by dispatching the appropriate route.
	 *
	 * @return void
	 * @throws RuntimeException If the application has already been executed.
	 */
	public function run(): void
	{
		// Prevent multiple executions
		static $hasRun = false;
		if ($hasRun) {
			$this->logMessage('ERROR', 'Application execution attempted multiple times');
			throw new RuntimeException("The application has already been executed.");
		}
		$hasRun = true;
		$this->logMessage('INFO', 'Application started');

		// Convert PHP errors to exceptions
		set_error_handler(function ($severity, $message, $file, $line) {
			$this->logMessage('ERROR', "PHP error: $message in $file:$line (severity: $severity)");
			throw new \ErrorException($message, 500, $severity, $file, $line);
		});

		try {
			// Dispatch the route
			$this->dispatchRoute();
		} catch (Exception $e) {
			// Handle the exception using the registered handler
			$handler = $this->getExceptionHandler($e);
			if ($handler) {
				$handler(new Request(), new Response(), $e);
			} else {
				// Fallback for unhandled exceptions
				$this->logMessage('ERROR', "Unhandled exception: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
				http_response_code(500);
				echo "<p>Unexpected error occurred.</p>";
				$this->logError($e);
			}
		} finally {
			// Restore default PHP error handler
			restore_error_handler();
			$this->logMessage('INFO', 'Application execution completed');
		}
	}

	/**
	 * Logs an exception to the error log file with request context.
	 *
	 * @param Exception $e The exception to log.
	 * @param Request|null $request The request object, if available.
	 * @return void
	 */
	private function logError(Exception $e, ?Request $request = null): void
	{
		// Format error message with timestamp, request details, and stack trace
		$timestamp = date('Y-m-d H:i:s');
		$requestInfo = $request ? "Request: {$request->getMethod()} {$request->getUri()}" : 'No request context';
		$message = "[{$timestamp}] [ERROR] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n";
		$message .= "$requestInfo\n";
		$message .= $e->getTraceAsString() . "\n\n";
		// Write to log file with size check
		$this->logMessage('ERROR', $message, false);
	}

	/**
	 * Logs a message to the log file, ensuring the file size does not exceed 3MB.
	 *
	 * @param string $level Log level (e.g., INFO, ERROR, WARNING).
	 * @param string $message Message to log.
	 * @param bool $append Whether to append to the log file (default: true).
	 * @return void
	 */
	private function logMessage(string $level, string $message, bool $append = true): void
	{
		if (is_null($this->logDir)) return;

		// Format log entry with timestamp and level
		$timestamp = date('Y-m-d H:i:s');
		$logEntry = "[{$timestamp}] [$level] $message\n";

		// Check if log file exists and its size
		if (file_exists($this->logFile) && filesize($this->logFile) >= self::MAX_LOG_SIZE) {
			// Rotate log file
			$this->rotateLogFile();
		}

		// Write or append to log file
		file_put_contents($this->logFile, $logEntry, $append ? FILE_APPEND : 0);
	}

	/**
	 * Rotates the log file if it exceeds the maximum size.
	 *
	 * @return void
	 */
	private function rotateLogFile(): void
	{
		// Determine the next available log file number
		for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
			$oldLog = $this->logFile . '.' . $i;
			$newLog = $this->logFile . '.' . ($i + 1);
			if (file_exists($oldLog)) {
				rename($oldLog, $newLog);
			}
		}

		// Rename current log file to .1
		if (file_exists($this->logFile)) {
			rename($this->logFile, $this->logFile . '.1');
		}

		// Create a new empty log file
		file_put_contents($this->logFile, '');
		$this->logMessage('INFO', 'Log file rotated', false);
	}

	/**
	 * Converts a route with parameters into a regex pattern.
	 *
	 * @param string $route Original route (e.g., /users/[id]).
	 * @return string Regex pattern (e.g., /^\/users\/([a-zA-Z0-9\.\-_]+)$/).
	 */
	private function parseRoute(string $route): string
	{
		// Normalize route by removing trailing slash
		$route = strlen($route) > 1 ? rtrim($route, '/') : $route;
		// Replace parameter placeholders with regex
		$route = preg_replace($this->routePattern, $this->paramPattern, $route);
		// Escape forward slashes and wrap in regex delimiters
		$route = str_replace('/', '\/', $route);
		$this->logMessage('INFO', "Parsed route: $route");
		return "/^{$route}$/";
	}

	/**
	 * Dispatches the route matching the current request.
	 *
	 * @return void
	 */
	private function dispatchRoute(): void
	{
		// Get request method and URI
		$method = $this->getMethod();
		$uri = $this->getUri();
		$params = [];

		// Log incoming request
		$this->logMessage('INFO', "Handling request: $method $uri");

		// Check for redirects
		if (isset($this->routes['redirects'])) {
			foreach ($this->routes['redirects'] as $redirect) {
				if ($uri === $redirect['from']) {
					$statusCode = $redirect['permanent'] ? '301 Moved Permanently' : '302 Found';
					$this->logMessage('INFO', "Redirecting from $uri to {$redirect['to']} ($statusCode)");
					header("HTTP/1.1 {$statusCode}");
					header("Location: {$redirect['to']}");
					exit;
				}
			}
		}

		// Check if routes exist for the method
		if (!isset($this->routes[$method])) {
			$this->logMessage('WARNING', "No routes defined for method: $method");
			$this->handleFallbackOrNotFound();
			return;
		}

		// Match route and execute handler
		foreach ($this->routes[$method] as $route => $routeData) {
			if (preg_match($route, $uri, $params)) {
				// Remove full match from parameters
				array_shift($params);
				$handler = $routeData['handler'];
				$paramNames = $routeData['paramNames'];

				// Create request, response, and parameter objects
				$request = new Request();
				$response = new Response();
				$paramObj = new stdClass();
				foreach ($paramNames as $index => $name) {
					$paramObj->$name = $params[$index] ?? null;
				}

				// Log route match and parameters
				$paramList = json_encode((array)$paramObj);
				$this->logMessage('INFO', "Route matched: $method $route with parameters: $paramList");

				// Execute the handler
				$handler($request, $response, $paramObj);
				return;
			}
		}

		// Handle unmatched routes
		$this->logMessage('WARNING', "No route matched for: $method $uri");
		$this->handleFallbackOrNotFound();
	}

	/**
	 * Handles unmatched routes or fallback scenarios.
	 *
	 * @return void
	 */
	private function handleFallbackOrNotFound(): void
	{
		// Use fallback handler if defined, otherwise apply 404 handler
		if (isset($this->routes['fallback'])) {
			$this->logMessage('INFO', 'Executing fallback handler');
			$this->routes['fallback']();
		} else {
			$this->logMessage('INFO', 'Executing 404 handler');
			$handler = $this->applyMiddlewares($this->_404, $this->globalMiddlewares);
			$handler(new Request(), new Response(), new stdClass());
		}
	}

	/**
	 * Retrieves the HTTP method of the current request.
	 *
	 * @return string HTTP method (e.g., GET).
	 */
	private function getMethod(): string
	{
		return strtoupper($_SERVER['REQUEST_METHOD']);
	}

	/**
	 * Retrieves the normalized URI of the current request.
	 *
	 * @return string Normalized URI.
	 */
	private function getUri(): string
	{
		$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		return strlen($uri) > 1 ? rtrim($uri, '/') : $uri;
	}

	/**
	 * Applies a chain of middlewares to a handler.
	 *
	 * @param callable $handler The final handler to execute.
	 * @param array $middlewares List of middlewares to apply.
	 * @return callable The wrapped handler with middlewares applied.
	 */
	private function applyMiddlewares(callable $handler, array $middlewares): callable
	{
		$next = $handler;
		$middlewares = array_reverse($middlewares);

		// Wrap each middleware around the handler
		foreach ($middlewares as $middleware) {
			$next = function (Request $req, Response $res, $params) use ($middleware, $next): void {
				$this->logMessage('INFO', 'Applying middleware for request: ' . $req->getUri());
				$middleware($req, $res, function (Request $req, Response $res, $params) use ($next) {
					$next($req, $res, $params);
				}, $params);
			};
		}

		return $next;
	}
}
