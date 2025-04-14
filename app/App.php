<?php

namespace AdaiasMagdiel\Erlenmeyer;

use AdaiasMagdiel\Hermes\Router;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Main application class for handling routing, assets, and HTTP responses.
 */
class App
{
	/**
	 * @var Assets Asset manager instance.
	 */
	private Assets $assets;

	/**
	 * @var Closure Custom 404 handler.
	 */
	private Closure $_404;

	/**
	 * @var array List of global middlewares.
	 */
	private array $globalMiddlewares = [];

	/**
	 * Constructs the application.
	 *
	 * @param string $assetsDir Directory for static assets (default: "/public"). Set to null to disable assets.
	 * @param string $assetsRoute Base route for serving assets (default: "/assets").
	 * @throws InvalidArgumentException If the assets directory is invalid or inaccessible, or if the assets route is malformed.
	 */
	public function __construct(private string $assetsDir = "/public", private string $assetsRoute = "/assets")
	{
		// Validate assets directory
		$this->assetsDir = realpath($assetsDir);
		if ($this->assetsDir === false || !is_dir($this->assetsDir) || !is_readable($this->assetsDir)) {
			throw new InvalidArgumentException("Invalid or inaccessible assets directory: $assetsDir");
		}

		// Validate assets route (relaxed to allow subdirectories)
		if (!preg_match('/^\/[a-zA-Z0-9_-]+(\/[a-zA-Z0-9_-]+)*\/?$/', $assetsRoute)) {
			throw new InvalidArgumentException("Invalid assets route: $assetsRoute");
		}

		$this->assets = new Assets($assetsDir, $assetsRoute);

		// Default 404 handler
		$this->set404Handler(function (Request $req, Response $res): void {
			$res
				->setStatusCode(404)
				->withHtml("<h1>404 Not Found</h1><p>Requested URI: {$req->getUri()}</p>")
				->send();
		});

		// Initialize router
		Router::initialize();

		// Set fallback handler
		Router::fallback(function () {
			if ($this->assets->isAssetRequest()) {
				$this->assets->serveAsset();
				return;
			}

			// Apply global middlewares to 404 handler
			$handler = $this->applyMiddlewares($this->_404, $this->globalMiddlewares);
			$handler(new Request(), new Response());
		});
	}

	/**
	 * Adds a global middleware to be applied to all routes.
	 *
	 * @param callable $middleware A callable that accepts a Request and a callable (next handler) and handles the response.
	 * @return void
	 */
	public function addMiddleware(callable $middleware): void
	{
		$this->globalMiddlewares[] = $middleware;
	}

	/**
	 * Sets a custom 404 error handler.
	 *
	 * @param callable $action A callable that accepts a Request and sends a response (e.g., via Response).
	 * @return void
	 */
	public function set404Handler(callable $action): void
	{
		$this->_404 = Closure::fromCallable($action);
	}

	/**
	 * Registers a route for a specific HTTP method.
	 *
	 * @param string $method HTTP method (e.g., GET, POST).
	 * @param string $route The route pattern to match.
	 * @param callable $action A callable that accepts a Request and handles the response.
	 * @param array $middlewares Optional list of middlewares specific to this route.
	 * @return void
	 * @throws InvalidArgumentException If the HTTP method is invalid.
	 */
	public function route(string $method, string $route, callable $action, array $middlewares = []): void
	{
		// Validate HTTP method
		$method = strtoupper(trim($method));
		if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'], true)) {
			throw new InvalidArgumentException("Invalid HTTP method: $method");
		}

		// Combine global and route-specific middlewares
		$allMiddlewares = array_merge($this->globalMiddlewares, $middlewares);

		// Apply middlewares to the action
		$handler = $this->applyMiddlewares($action, $allMiddlewares);

		Router::route($method, $route, function () use ($handler) {
			$handler(new Request(), new Response());
		});
	}

	/**
	 * Registers a GET route.
	 *
	 * @param string $route The route pattern to match.
	 * @param callable $action A callable that accepts a Request and handles the response.
	 * @param array $middlewares Optional list of middlewares specific to this route.
	 * @return void
	 */
	public function get(string $route, callable $action, array $middlewares = []): void
	{
		$this->route("GET", $route, $action, $middlewares);
	}

	/**
	 * Registers a POST route.
	 *
	 * @param string $route The route pattern to match.
	 * @param callable $action A callable that accepts a Request and handles the response.
	 * @param array $middlewares Optional list of middlewares specific to this route.
	 * @return void
	 */
	public function post(string $route, callable $action, array $middlewares = []): void
	{
		$this->route("POST", $route, $action, $middlewares);
	}

	/**
	 * Executes the application by dispatching the router.
	 *
	 * @return void
	 * @throws RuntimeException If the application has already been executed.
	 */
	public function run(): void
	{
		static $hasRun = false;
		if ($hasRun) {
			throw new RuntimeException("The application has already been executed.");
		}

		$hasRun = true;
		Router::execute();
	}

	/**
	 * Applies a chain of middlewares to a handler.
	 *
	 * @param callable $handler The final handler (action or 404 handler).
	 * @param array $middlewares List of middlewares to apply.
	 * @return callable The wrapped handler with middlewares applied.
	 */
	private function applyMiddlewares(callable $handler, array $middlewares): callable
	{
		$next = $handler;
		$middlewares = array_reverse($middlewares);

		// Apply middlewares in reverse order to ensure proper nesting
		foreach ($middlewares as $middleware) {
			$next = function (Request $req, Response $res) use ($middleware, $next): void {
				$middleware($req, $res, $next);
			};
		}

		return $next;
	}
}
