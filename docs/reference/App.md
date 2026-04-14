# Class: `AdaiasMagdiel\Erlenmeyer\App`

**Namespace:** `AdaiasMagdiel\Erlenmeyer`  
**Defined in:** `app/App.php`

---

## Overview

The `App` class is the core of the Erlenmeyer framework.  
It provides routing, middleware, error management, and logging.

---

## Properties

| Name                 | Visibility | Type               | Description                                              |
| -------------------- | ---------- | ------------------ | -------------------------------------------------------- |
| `$logger`            | private    | `LoggerInterface`  | Logger instance for application events and errors.       |
| `$router`            | private    | `Router`           | Handles route registration and matching.                 |
| `$exceptionHandler`  | private    | `ExceptionHandler` | Manages exception handler registration and dispatch.     |
| `$globalMiddlewares` | private    | `array`            | Global middlewares executed on every request.            |
| `$notFoundHandler`   | private    | `Closure`          | Default 404 handler.                                     |
| `$fallbackHandler`   | private    | `?Closure`         | Optional fallback handler called before 404.             |

---

## Constructor

### `__construct(?LoggerInterface $logger = null)`

Initializes the application, starts the session, sets up the router, exception handler,
and registers default 404 and 500 handlers.

#### Parameters

| Name      | Type               | Description                                                                  |
| --------- | ------------------ | ---------------------------------------------------------------------------- |
| `$logger` | `?LoggerInterface` | Optional logger. Defaults to `NullLogger` (all log calls are discarded).     |

#### Default Behavior

When no logger is provided, a `NullLogger` is used — a no-op implementation that discards
all log messages silently. To enable logging, pass a `FileLogger` or `ConsoleLogger`:

```php
use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Logging\FileLogger;

$app = new App(new FileLogger(__DIR__ . '/logs'));
```

---

## Public Methods

### `setExceptionHandler(string $throwableClass, callable $handler): void`

Registers a handler for a specific exception type.

#### Parameters

| Name              | Type       | Description                                                     |
| ----------------- | ---------- | --------------------------------------------------------------- |
| `$throwableClass` | `string`   | Exception class name (must extend `Throwable`).                 |
| `$handler`        | `callable` | Handler callable `(Request $req, Response $res, Throwable $e)`. |

#### Throws

- `InvalidArgumentException` – If `$throwableClass` is not a subclass of `Throwable`.

---

### `route(string $method, string $route, callable $action, array $middlewares = []): void`

Registers a route with an HTTP method and handler.

#### Parameters

| Name           | Type       | Description                                                |
| -------------- | ---------- | ---------------------------------------------------------- |
| `$method`      | `string`   | HTTP method (`GET`, `POST`, `PUT`, etc.).                  |
| `$route`       | `string`   | Route pattern (supports `[param]` placeholders).           |
| `$action`      | `callable` | Handler `(Request $req, Response $res, stdClass $params)`. |
| `$middlewares` | `array`    | Optional middlewares specific to the route.                |

#### Throws

- `InvalidArgumentException` – If method is invalid.

---

### HTTP Method Helpers

| Method                                                                                  | Description                                    |
| --------------------------------------------------------------------------------------- | ---------------------------------------------- |
| `get(string $route, callable $action, array $middlewares = []): void`                   | Registers a `GET` route.                       |
| `post(string $route, callable $action, array $middlewares = []): void`                  | Registers a `POST` route.                      |
| `put(string $route, callable $action, array $middlewares = []): void`                   | Registers a `PUT` route.                       |
| `delete(string $route, callable $action, array $middlewares = []): void`                | Registers a `DELETE` route.                    |
| `patch(string $route, callable $action, array $middlewares = []): void`                 | Registers a `PATCH` route.                     |
| `options(string $route, callable $action, array $middlewares = []): void`               | Registers an `OPTIONS` route.                  |
| `head(string $route, callable $action, array $middlewares = []): void`                  | Registers a `HEAD` route.                      |
| `any(string $route, callable $action, array $middlewares = []): void`                   | Registers a handler for all HTTP methods.      |
| `match(array $methods, string $route, callable $action, array $middlewares = []): void` | Registers a handler for specific HTTP methods. |

---

### `redirect(string $from, string $to, bool $permanent = false): void`

Registers an internal redirect from one path to another.

#### Parameters

| Name         | Type     | Description                                        |
| ------------ | -------- | -------------------------------------------------- |
| `$from`      | `string` | Source path.                                       |
| `$to`        | `string` | Target path.                                       |
| `$permanent` | `bool`   | Whether to use HTTP 301 (`true`) or 302 (`false`). |

---

### `set404Handler(callable $action): void`

Registers a custom handler for **404 Not Found** responses.

The handler must be a callable function with the following signature:

```php
function (Request $req, Response $res, stdClass $params): void
```

#### Parameters

| Name      | Type       | Description                                                                                                                    |
| --------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `$action` | `callable` | Function or closure executed when no route matches the request. Must accept `(Request $req, Response $res, stdClass $params)`. |

---

### `setFallbackHandler(callable $action): void`

Registers a **fallback handler** invoked when no route matches, before the 404 handler.

The fallback handler receives the same signature as route handlers:

```php
function (Request $req, Response $res, stdClass $params): void
```

#### Parameters

| Name      | Type       | Description                                                                       |
| --------- | ---------- | --------------------------------------------------------------------------------- |
| `$action` | `callable` | Callable invoked when no route matches. Receives `(Request, Response, stdClass)`. |

#### Behavior

- If a fallback handler is registered, it is called instead of the 404 handler.
- Global middlewares are **not** applied to the fallback handler.
- If the fallback handler does not send a response, execution stops there — no automatic 404 is triggered.

#### Use Cases

- Single-page applications (SPA) that need to serve `index.html` for all unmatched routes
- Catch-all proxy patterns
- Custom "not found" logic that attempts something before returning 404

#### Example — SPA catch-all

```php
$app->setFallbackHandler(function (Request $req, Response $res, $params) {
    $res->withTemplate(__DIR__ . '/views/app.html')->send();
});
```

#### Example — Try a CMS before 404

```php
$app->setFallbackHandler(function (Request $req, Response $res, $params) {
    $page = CMS::findPage($req->getUri());

    if ($page) {
        $res->withHtml($page->render())->send();
    } else {
        $res->setStatusCode(404)->withHtml('<h1>Page Not Found</h1>')->send();
    }
});
```

---

### `addMiddleware(callable $middleware): void`

Adds a global middleware applied to all requests.

A middleware is a **callable function** expected to follow this signature:

```php
function (Request $req, Response $res, callable $next, stdClass $params): void
```

Each middleware can:

- Access and modify the `Request` and `Response` objects.
- Call `$next($req, $res, $params)` to continue execution.
- Stop the chain (e.g., to send an error or custom response).
- Optionally inject data into `$params` for downstream handlers.

#### Parameters

| Name          | Type       | Description                                                                                                               |
| ------------- | ---------- | ------------------------------------------------------------------------------------------------------------------------- |
| `$middleware` | `callable` | A function or closure that matches the signature `(Request $req, Response $res, callable $next, stdClass $params): void`. |

---

### `run(): void`

Executes the application lifecycle:

- Registers PHP error/shutdown handlers
- Creates request/response instances
- Dispatches matched routes or assets
- Sends the final response

#### Throws

- `RuntimeException` – If execution fails or headers cannot be sent.

---

### `handle(Request $req, Response $res): Response`

Processes a manually supplied request and returns a `Response`.

#### Parameters

| Name   | Type       | Description        |
| ------ | ---------- | ------------------ |
| `$req` | `Request`  | Request instance.  |
| `$res` | `Response` | Response instance. |

#### Returns

`Response` – Populated response after route handling.

---

## Private Methods

### `handleFallbackOrNotFound(Request $req, Response $res): void`

Called when no route matches. Executes the fallback handler if registered,
otherwise applies global middlewares and invokes the 404 handler.

| Name   | Type       | Description          |
| ------ | ---------- | -------------------- |
| `$req` | `Request`  | The current request. |
| `$res` | `Response` | The response object. |

---

### `handleException(Throwable $e, Request $req, Response $res): void`

Delegates to the `ExceptionHandler` to find and invoke the most specific registered
handler for the thrown exception. Falls back to a plain 500 response if none matches.

| Name   | Type        | Description                |
| ------ | ----------- | -------------------------- |
| `$e`   | `Throwable` | The exception.             |
| `$req` | `Request`   | The current request.       |
| `$res` | `Response`  | The current response.      |

---

### `applyMiddlewares(callable $handler, array $middlewares): callable`

Wraps a handler with a stack of middleware closures using reverse iteration.

| Name           | Type       | Description                   |
| -------------- | ---------- | ----------------------------- |
| `$handler`     | `callable` | The final route handler.      |
| `$middlewares` | `array`    | List of middlewares to apply. |

**Returns:** `callable` — Composed callable chain with all middlewares applied.

---

## Behavior Summary

| Feature            | Description                                                            |
| ------------------ | ---------------------------------------------------------------------- |
| Routing            | Regex-based pattern matching with dynamic parameters `[param]`.        |
| Middlewares        | Global and route-specific, with `$next` chaining.                      |
| Exception Handling | Per-class exception mapping with class hierarchy traversal.            |
| Fallback Handler   | Optional catch-all called before 404, bypasses global middlewares.     |
| Logging            | Delegated to `LoggerInterface`. Defaults to `NullLogger` (no output).  |

---

## See Also

- [`Router`](./Router.md)
- [`ExceptionHandler`](./ExceptionHandler.md)
- [`Request`](./Request.md)
- [`Response`](./Response.md)
- [`Session`](./Session.md)
- [`Logging\LoggerInterface`](./Logging/LoggerInterface.md)
