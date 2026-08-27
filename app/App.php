<?php

namespace AdaiasMagdiel\Erlenmeyer;

use AdaiasMagdiel\Erlenmeyer\Exception\Handler as ExceptionHandler;
use Closure;
use ErrorException;
use stdClass;
use Throwable;

/**
 * Main application class acting as the coordinator.
 *
 * It delegates routing logic to the Router class and exception handling
 * to the ExceptionHandler class, maintaining a clean separation of concerns.
 */
class App
{
	private Router $router;
	private ExceptionHandler $exceptionHandler;

	/** @var array List of global middlewares applied to all routes. */
	private array $globalMiddlewares = [];

	/** @var array Stack of active route prefix segments. */
	private array $prefixStack = [];

	/** @var array Stack of middleware arrays per active group. */
	private array $middlewareStack = [];

	/** @var Closure Default 404 handler. */
	private Closure $notFoundHandler;

	/** @var Closure|null Custom fallback handler. */
	private ?Closure $fallbackHandler = null;

	public function __construct()
	{
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		$this->router = new Router();
		$this->exceptionHandler = new ExceptionHandler();

		// Default 404 Handler
		$this->set404Handler(function (Request $req, Response $res) {
			$res->setStatusCode(404)
				->withHtml("<h1>404 Not Found</h1><p>Requested URI: " . htmlspecialchars($req->getUri()) . "</p>")
				->send();
		});

		// Default Exception Handler (Safe against XSS)
		$this->setExceptionHandler(Throwable::class, function (Request $req, Response $res, Throwable $e) {
			$msg = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			$res->setStatusCode(500)
				->withHtml("<h1>500 Internal Server Error</h1><p>Error: {$msg}</p>")
				->send();
		});
	}

	// -------------------------------------------------------------------------
	// Configuration & Registration Methods
	// -------------------------------------------------------------------------

	public function setTrustedProxies(array $ips): void
	{
		Request::setTrustedProxies($ips);
	}

	public function setExceptionHandler(string $throwableClass, callable $handler): void
	{
		$this->exceptionHandler->register($throwableClass, $handler);
	}

	public function set404Handler(callable $action): void
	{
		$this->notFoundHandler = Closure::fromCallable($action);
	}

	public function setFallbackHandler(callable $action): void
	{
		$this->fallbackHandler = Closure::fromCallable($action);
	}

	public function addMiddleware(callable $middleware): void
	{
		$this->globalMiddlewares[] = $middleware;
	}

	// -------------------------------------------------------------------------
	// Routing Proxy Methods
	// -------------------------------------------------------------------------

	public function group(string $prefix, callable $callback, array $middlewares = []): void
	{
		$this->prefixStack[] = rtrim($prefix, '/');
		$this->middlewareStack[] = $middlewares;
		try {
			$callback();
		} finally {
			array_pop($this->prefixStack);
			array_pop($this->middlewareStack);
		}
	}

	private function buildRoute(string $route): string
	{
		$prefix = implode('', $this->prefixStack);
		if ($prefix === '') return $route;
		if ($route === '/') return $prefix;
		return $prefix . $route;
	}

	private function buildMiddlewares(array $routeMiddlewares): array
	{
		$all = [];
		foreach ($this->middlewareStack as $groupMiddlewares) {
			$all = array_merge($all, $groupMiddlewares);
		}
		return array_merge($all, $routeMiddlewares);
	}

	public function route(string $method, string $route, callable $action, array $middlewares = []): void
	{
		$this->router->add($method, $this->buildRoute($route), $action, $this->buildMiddlewares($middlewares));
	}

	public function get(string $route, callable $action, array $middlewares = []): void
	{
		$this->router->add('GET', $this->buildRoute($route), $action, $this->buildMiddlewares($middlewares));
	}

	public function post(string $route, callable $action, array $middlewares = []): void
	{
		$this->router->add('POST', $this->buildRoute($route), $action, $this->buildMiddlewares($middlewares));
	}

	public function put(string $route, callable $action, array $middlewares = []): void
	{
		$this->router->add('PUT', $this->buildRoute($route), $action, $this->buildMiddlewares($middlewares));
	}

	public function delete(string $route, callable $action, array $middlewares = []): void
	{
		$this->router->add('DELETE', $this->buildRoute($route), $action, $this->buildMiddlewares($middlewares));
	}

	public function patch(string $route, callable $action, array $middlewares = []): void
	{
		$this->router->add('PATCH', $this->buildRoute($route), $action, $this->buildMiddlewares($middlewares));
	}

	public function options(string $route, callable $action, array $middlewares = []): void
	{
		$this->router->add('OPTIONS', $this->buildRoute($route), $action, $this->buildMiddlewares($middlewares));
	}

	public function head(string $route, callable $action, array $middlewares = []): void
	{
		$this->router->add('HEAD', $this->buildRoute($route), $action, $this->buildMiddlewares($middlewares));
	}

	public function any(string $route, callable $action, array $middlewares = []): void
	{
		$built = $this->buildRoute($route);
		$builtMiddlewares = $this->buildMiddlewares($middlewares);
		foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'] as $method) {
			$this->router->add($method, $built, $action, $builtMiddlewares);
		}
	}

	public function match(array $methods, string $route, callable $action, array $middlewares = []): void
	{
		$built = $this->buildRoute($route);
		$builtMiddlewares = $this->buildMiddlewares($middlewares);
		foreach ($methods as $method) {
			$this->router->add(strtoupper($method), $built, $action, $builtMiddlewares);
		}
	}

	public function redirect(string $from, string $to, bool $permanent = false): void
	{
		$this->router->redirect($this->buildRoute($from), $to, $permanent);
	}

	// -------------------------------------------------------------------------
	// Execution Core
	// -------------------------------------------------------------------------

	public function run(): void
	{
		set_error_handler(function ($severity, $message, $file, $line) {
			throw new ErrorException($message, 500, $severity, $file, $line);
		});

		register_shutdown_function(function () {
			$error = error_get_last();
			if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
				if (ob_get_length()) ob_clean();

				http_response_code(500);

				$isJson = (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
					|| (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

				if ($isJson) {
					header('Content-Type: application/json; charset=UTF-8');
					echo json_encode(['error' => true, 'message' => 'Fatal Error']);
				} else {
					$safeMsg = htmlspecialchars($error['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
					echo "<h1>Fatal Error</h1><p>{$safeMsg}</p>";
				}
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
			$this->handleException($e, new Request(), new Response());
		} finally {
			restore_error_handler();
		}
	}

	public function handle(Request $req, Response $res): Response
	{
		try {
			$match = $this->router->match($req->getMethod(), $req->getUri());

			if ($match) {
				if ($match['type'] === 'redirect') {
					return $res->redirect($match['to'], $match['status']);
				}

				if ($match['type'] === 'route') {
					$allMiddlewares = array_merge($this->globalMiddlewares, $match['middlewares']);
					$handler = $this->applyMiddlewares($match['handler'], $allMiddlewares);

					$handler($req, $res, $match['params']);
					return $res;
				}
			}

			// No route found
			$this->handleFallbackOrNotFound($req, $res);
			return $res;
		} catch (Throwable $e) {
			$this->handleException($e, $req, $res);
			return $res;
		}
	}

	private function handleException(Throwable $e, Request $req, Response $res): void
	{
		$handler = $this->exceptionHandler->getHandler($e);
		if ($handler) {
			$handler($req, $res, $e);
			if (!$res->isSent()) $res->send();
		} else {
			// Ultimate fallback if no handler is registered even for Throwable
			http_response_code(500);
			echo "Unexpected error.";
		}
	}

	private function handleFallbackOrNotFound(Request $req, Response $res): void
	{
		if ($this->fallbackHandler) {
			($this->fallbackHandler)($req, $res, new stdClass());
		} else {
			$handler = $this->applyMiddlewares($this->notFoundHandler, $this->globalMiddlewares);
			$handler($req, $res, new stdClass());
		}
	}

	private function applyMiddlewares(callable $handler, array $middlewares): callable
	{
		$next = $handler;
		$middlewares = array_reverse($middlewares);

		foreach ($middlewares as $middleware) {
			$next = function (Request $req, Response $res, $params) use ($middleware, $next): void {
				$middleware($req, $res, function (Request $req, Response $res, $params) use ($next) {
					$next($req, $res, $params);
				}, $params);
			};
		}

		return $next;
	}
}
