<?php

namespace AdaiasMagdiel\Erlenmeyer;

use AdaiasMagdiel\Erlenmeyer\Logging\LoggerInterface;
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;
use AdaiasMagdiel\Erlenmeyer\Logging\NullLogger;
use Closure;
use ErrorException;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Main application class for handling HTTP routing, assets, middlewares, and exception handling.
 *
 * This class serves as the core of the application, managing routes, serving static assets,
 * applying middlewares, and handling exceptions. It supports dynamic routes, custom error
 * handling, and is designed for extensibility in projects.
 */
class App
{
	/**
	 * @var LoggerInterface $logger
	 * Logger interface for handling system logging.
	 * Allows logging of various levels (debug, info, warning, error, etc.) and exceptions.
	 */
	private LoggerInterface $logger;

	/** @var Closure Custom handler for 404 errors. */
	private Closure $_404;

	/** @var array List of global middlewares applied to all routes. */
	private array $globalMiddlewares = [];

	/** @var array Map of exception classes to their respective handlers. */
	private array $exceptionHandlers = [];

	/** @var array Storage for routes, indexed by HTTP method (e.g., ['GET' => ['/route' => handler]]). */
	private array $routes = [];

	/** @var string Regex pattern for matching route parameters (e.g., /[param]). */
	private string $routePattern = '/\/\[[a-zA-Z0-9\.\-_]+\]/';

	/** @var string Regex replacement for route parameters (e.g., ([a-zA-Z0-9\.\-_]+)). */
	private string $paramPattern = '/([a-zA-Z0-9\.\-_]+)';

	/**
	 * Constructs the application instance.
	 *
	 * Initializes the asset manager, default 404 handler, default exception handler,
	 * and fallback route for handling asset requests or 404 errors.
	 *
	 * @param ?LoggerInterface $logger Logger instance for application logging.
	 */
	public function __construct(
		?LoggerInterface $logger = null
	) {
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		if (is_null($logger)) {
			$this->logger = new NullLogger();
		} else {
			$this->logger = $logger;
		}

		$this->set404Handler(function (Request $req, Response $res, $params): void {
			$res
				->setStatusCode(404)
				->withHtml("<h1>404 Not Found</h1><p>Requested URI: {$req->getUri()}</p>")
				->send();
		});

		$this->setExceptionHandler(Throwable::class, function (Request $req, Response $res, Throwable $e) {
			$res
				->setStatusCode(500)
				->withHtml("<h1>500 Internal Server Error</h1><p>Error: {$e->getMessage()}</p>")
				->send();

			$this->logger->logException($e, $req);
		});


		$this->routes['404'] = $this->_404;
		$this->routes['fallback'] = function (?Request $req = null, ?Response $res = null, ?stdClass $params = null) {
			$this->logger->log(LogLevel::WARNING, 'No route matched, executing 404 handler for URI: ' . $req->getUri());
			$handler = $this->applyMiddlewares($this->_404, $this->globalMiddlewares);

			if (is_null($req)) $req = new Request();
			if (is_null($res)) $res = new Response();
			if (is_null($params)) $params = new stdClass();

			$handler($req, $res, $params);
		};
	}

	/**
	 * Registers a handler for a specific throwable type (Exception or Error).
	 *
	 * This allows customizing the application's behavior for different
	 * error or exception types. Handlers are executed in order of specificity,
	 * meaning subclasses are matched before their parent types.
	 *
	 * Example:
	 * ```php
	 * $app->setExceptionHandler(TypeError::class, function ($req, $res, $e) {
	 *     $res->withText("Invalid type: {$e->getMessage()}")->setStatusCode(400)->send();
	 * });
	 * ```
	 *
	 * @param string $throwableClass Fully qualified class name of the Throwable to handle.
	 * @param callable $handler A callable that receives (Request $req, Response $res, Throwable $e).
	 * @return void
	 *
	 * @throws InvalidArgumentException If the class does not exist or is not a subclass of Throwable.
	 */
	public function setExceptionHandler(string $throwableClass, callable $handler): void
	{
		if (!is_a($throwableClass, Throwable::class, true)) {
			throw new InvalidArgumentException("Invalid throwable class: $throwableClass");
		}


		$this->exceptionHandlers[$throwableClass] = Closure::fromCallable($handler);
		$this->logger->log(LogLevel::INFO, "Exception handler registered for class: $throwableClass");
	}



	/**
	 * Retrieves the handler for a thrown Throwable, traversing the class hierarchy.
	 *
	 * @param Throwable $e The thrown error or exception.
	 * @return Closure|null The corresponding handler or null if none is found.
	 */
	public function getExceptionHandler(Throwable $e): ?Closure
	{
		$class = get_class($e);
		while ($class && isset($this->exceptionHandlers[$class])) {
			return $this->exceptionHandlers[$class];
		}

		$parent = get_parent_class($class);
		while ($parent) {
			if (isset($this->exceptionHandlers[$parent])) {
				return $this->exceptionHandlers[$parent];
			}
			$parent = get_parent_class($parent);
		}

		return $this->exceptionHandlers[Throwable::class] ?? null;
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
		$method = strtoupper(trim($method));
		if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'], true)) {
			$this->logger->log(LogLevel::ERROR, "Invalid HTTP method: $method for route: $route");
			throw new InvalidArgumentException("Invalid HTTP method: $method");
		}

		$formattedRoute = $this->parseRoute($route);
		$allMiddlewares = array_merge($this->globalMiddlewares, $middlewares);
		$handler = $this->applyMiddlewares($action, $allMiddlewares);

		if (!isset($this->routes[$method])) {
			$this->routes[$method] = [];
		}

		preg_match_all('/\[([a-zA-Z0-9\.\-_]+)\]/', $route, $matches);
		$paramNames = $matches[1] ?? [];

		$this->routes[$method][$formattedRoute] = [
			'handler' => $handler,
			'paramNames' => $paramNames
		];
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
	 * Registers a PUT route.
	 *
	 * @param string $route Route pattern.
	 * @param callable $action Action to execute.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 */
	public function put(string $route, callable $action, array $middlewares = []): void
	{
		$this->route('PUT', $route, $action, $middlewares);
	}

	/**
	 * Registers a DELETE route.
	 *
	 * @param string $route Route pattern.
	 * @param callable $action Action to execute.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 */
	public function delete(string $route, callable $action, array $middlewares = []): void
	{
		$this->route('DELETE', $route, $action, $middlewares);
	}

	/**
	 * Registers a PATCH route.
	 *
	 * @param string $route Route pattern.
	 * @param callable $action Action to execute.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 */
	public function patch(string $route, callable $action, array $middlewares = []): void
	{
		$this->route('PATCH', $route, $action, $middlewares);
	}

	/**
	 * Registers an OPTIONS route.
	 *
	 * @param string $route Route pattern.
	 * @param callable $action Action to execute.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 */
	public function options(string $route, callable $action, array $middlewares = []): void
	{
		$this->route('OPTIONS', $route, $action, $middlewares);
	}

	/**
	 * Registers a HEAD route.
	 *
	 * @param string $route Route pattern.
	 * @param callable $action Action to execute.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 */
	public function head(string $route, callable $action, array $middlewares = []): void
	{
		$this->route('HEAD', $route, $action, $middlewares);
	}

	/**
	 * Registers a route that matches any HTTP method.
	 *
	 * @param string $route Route pattern.
	 * @param callable $action Action to execute.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 */
	public function any(string $route, callable $action, array $middlewares = []): void
	{
		$methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
		foreach ($methods as $method) {
			$this->route($method, $route, $action, $middlewares);
		}
	}

	/**
	 * Registers multiple routes with the same action and middlewares.
	 *
	 * @param array $methods Array of HTTP methods (e.g., ['GET', 'POST']).
	 * @param string $route Route pattern.
	 * @param callable $action Action to execute.
	 * @param array $middlewares Route-specific middlewares.
	 * @return void
	 */
	public function match(array $methods, string $route, callable $action, array $middlewares = []): void
	{
		foreach ($methods as $method) {
			$this->route(strtoupper($method), $route, $action, $middlewares);
		}
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
		if (!isset($this->routes['redirects'])) {
			$this->routes['redirects'] = [];
		}

		$this->routes['redirects'][] = [
			'from' => $from,
			'to' => $to,
			'permanent' => $permanent
		];
	}

	/**
	 * Sets the custom 404 error handler.
	 *
	 * @param callable $action Action to execute for 404 errors.
	 * @return void
	 */
	public function set404Handler(callable $action): void
	{
		$this->_404 = Closure::fromCallable($action);
		$this->routes['404'] = $this->_404;
		$this->logger->log(LogLevel::INFO, 'Custom 404 handler set');
	}

	/**
	 * Adds a global middleware to be applied to all routes.
	 *
	 * @param callable $middleware Middleware to apply.
	 * @return void
	 */
	public function addMiddleware(callable $middleware): void
	{
		$this->globalMiddlewares[] = $middleware;
		$this->logger->log(LogLevel::INFO, 'Global middleware added');
	}

	/**
	 * Executes the application by dispatching the appropriate route.
	 *
	 * @return void
	 * @throws RuntimeException If the application has already been executed.
	 */
	public function run(): void
	{
		set_error_handler(function ($severity, $message, $file, $line) {
			$this->logger->log(LogLevel::ERROR, "PHP error: $message in $file:$line (severity: $severity)");
			throw new ErrorException($message, 500, $severity, $file, $line);
		});

		register_shutdown_function(function () {
			$error = error_get_last();
			if ($error !== null && in_array($error['type'], [
				E_ERROR,
				E_PARSE,
				E_CORE_ERROR,
				E_COMPILE_ERROR
			], true)) {
				http_response_code(500);
				echo "<h1>Fatal Error</h1><p>{$error['message']}</p>";
			}
		});

		try {
			$request = new Request();
			$response = new Response();

			$res = $this->handle($request, $response);
			if ($res && !$res->isSent()) {
				$res->send();
			}
		} catch (Throwable $e) {
			$handler = $this->getExceptionHandler($e);
			if ($handler) {
				$res = $handler(new Request(), new Response(), $e);
				if ($res && !$res->isSent()) {
					$res->send();
				}
			} else {
				http_response_code(500);
				echo "<p>Unexpected error occurred.</p>";
				$this->logger->logException($e);
			}
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Handles a custom request and returns the response.
	 *
	 * @param Request $req The request to handle.
	 * @param Response $res The response object.
	 * @return Response The processed response.
	 */
	public function handle(Request $req, Response $res): Response
	{
		$this->logger->log(LogLevel::INFO, 'Handling custom request via handle()');

		try {
			$method = $req->getMethod();
			$uri = $req->getUri();
			$params = [];

			if (isset($this->routes['redirects'])) {
				foreach ($this->routes['redirects'] as $redirect) {
					if ($uri === $redirect['from']) {
						$statusCode = $redirect['permanent'] ? 301 : 302;
						return $res->redirect($redirect['to'], $statusCode);
					}
				}
			}

			if (!isset($this->routes[$method])) {
				$this->logger->log(LogLevel::WARNING, "No routes defined for method: $method");
				$this->handleFallbackOrNotFound($req, $res);
				return $res;
			}

			foreach ($this->routes[$method] as $route => $routeData) {
				if (preg_match($route, $uri, $params)) {
					array_shift($params);
					$paramObj = new stdClass();
					foreach ($routeData['paramNames'] as $i => $name) {
						$paramObj->$name = $params[$i] ?? null;
					}

					$handler = $routeData['handler'];
					$handler($req, $res, $paramObj);
					return $res;
				}
			}

			$this->logger->log(LogLevel::WARNING, "No route matched for: $method $uri");
			$this->handleFallbackOrNotFound($req, $res);
			return $res;
		} catch (Throwable $e) {
			$handler = $this->getExceptionHandler($e);
			if ($handler) {
				$handler($req, $res, $e);
			} else {
				$this->logger->log(LogLevel::ERROR, "Unhandled exception: {$e->getMessage()}");
				$res->setStatusCode(500)->withText("Unexpected error: {$e->getMessage()}");
			}
			return $res;
		}
	}

	/**
	 * Converts a route with parameters into a regex pattern.
	 *
	 * @param string $route Original route (e.g., /users/[id]).
	 * @return string Regex pattern (e.g., /^\/users\/([a-zA-Z0-9\.\-_]+)$/).
	 */
	private function parseRoute(string $route): string
	{
		$route = strlen($route) > 1 ? rtrim($route, '/') : $route;
		$route = preg_replace($this->routePattern, $this->paramPattern, $route);
		$route = str_replace('/', '\/', $route);
		return "/^{$route}$/";
	}

	/**
	 * Handles unmatched routes or fallback scenarios.
	 *
	 * @param Request $req The request object.
	 * @param Response $res The response object.
	 * @return void
	 */
	private function handleFallbackOrNotFound(Request $req, Response $res): void
	{
		if (isset($this->routes['fallback'])) {
			$this->logger->log(LogLevel::INFO, 'Executing fallback handler');
			$this->routes['fallback']($req, $res, new stdClass());
		} else {
			$this->logger->log(LogLevel::INFO, 'Executing 404 handler');
			$handler = $this->applyMiddlewares($this->_404, $this->globalMiddlewares);
			$handler($req, $res, new stdClass());
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
		$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? "";
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

		foreach ($middlewares as $middleware) {
			$next = function (Request $req, Response $res, $params) use ($middleware, $next): void {
				$this->logger->log(LogLevel::INFO, 'Applying middleware for request: ' . $req->getUri());
				$middleware($req, $res, function (Request $req, Response $res, $params) use ($next) {
					$next($req, $res, $params);
				}, $params);
			};
		}

		return $next;
	}
}
