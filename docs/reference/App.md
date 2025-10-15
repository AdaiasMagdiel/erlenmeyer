# Class: `AdaiasMagdiel\Erlenmeyer\App`

**Namespace:** `AdaiasMagdiel\Erlenmeyer`  
**Defined in:** `app/App.php`

---

## Overview

The `App` class is the core of the Erlenmeyer framework.  
It provides routing, middleware, static asset handling, error management, and logging.

---

## Properties

| Name                 | Visibility | Type              | Description                                              |
| -------------------- | ---------- | ----------------- | -------------------------------------------------------- |
| `$assets`            | private    | `?Assets`         | Manages static file serving.                             |
| `$logger`            | private    | `LoggerInterface` | Logger instance for application events and errors.       |
| `$_404`              | private    | `Closure`         | Custom 404 handler.                                      |
| `$globalMiddlewares` | private    | `array`           | Global middlewares executed on every request.            |
| `$exceptionHandlers` | private    | `array`           | Map of exception classes to their handlers.              |
| `$routes`            | private    | `array`           | Collection of registered routes, grouped by HTTP method. |
| `$routePattern`      | private    | `string`          | Regex for identifying dynamic parameters in routes.      |
| `$paramPattern`      | private    | `string`          | Regex pattern used to replace route parameters.          |

---

## Constructor

### `__construct(?Assets $assets = null, ?LoggerInterface $logger = null)`

Initializes the application, logger, asset manager, and default error handlers.

#### Parameters

| Name      | Type               | Description                                  |
| --------- | ------------------ | -------------------------------------------- |
| `$assets` | `?Assets`          | Optional `Assets` instance for static files. |
| `$logger` | `?LoggerInterface` | Optional logger. Defaults to `FileLogger`.   |

#### Throws

- `InvalidArgumentException` – If the assets directory or route is invalid.

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

### `getExceptionHandler(Throwable $e): ?Closure`

Retrieves the most specific registered handler for a thrown exception.

#### Parameters

| Name | Type        | Description         |
| ---- | ----------- | ------------------- |
| `$e` | `Throwable` | Exception instance. |

#### Returns

`?Closure` – Matching handler closure or `null` if none found.

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

### `parseRoute(string $route): string`

Converts a route string into a regex pattern.

#### Parameters

| Name     | Type     | Description                         |
| -------- | -------- | ----------------------------------- |
| `$route` | `string` | Route string (e.g., `/users/[id]`). |

#### Returns

`string` – Regex expression for matching the route.

---

### `handleFallbackOrNotFound(Request $req, Response $res): void`

Handles unmatched requests by serving assets or invoking the 404 handler.

#### Parameters

| Name   | Type       | Description          |
| ------ | ---------- | -------------------- |
| `$req` | `Request`  | The current request. |
| `$res` | `Response` | The response object. |

---

### `getMethod(): string`

Retrieves the HTTP method from the environment.

#### Returns

`string` – Uppercase HTTP method name.

---

### `getUri(): string`

Retrieves and normalizes the request URI.

#### Returns

`string` – Normalized path (trailing slash removed except for `/`).

---

### `applyMiddlewares(callable $handler, array $middlewares): callable`

Wraps a handler with a stack of middleware closures.

#### Parameters

| Name           | Type       | Description                   |
| -------------- | ---------- | ----------------------------- |
| `$handler`     | `callable` | The final route handler.      |
| `$middlewares` | `array`    | List of middlewares to apply. |

#### Returns

`callable` – Composed callable chain with middleware wrapping.

---

## Behavior Summary

| Feature            | Description                                                     |
| ------------------ | --------------------------------------------------------------- |
| Routing            | Regex-based pattern matching with dynamic parameters `[param]`. |
| Middlewares        | Global and route-specific, with `$next` chaining.               |
| Exception Handling | Per-class exception mapping and fallback.                       |
| Asset Handling     | Delegated to `Assets` manager when provided.                    |
| Logging            | Delegated to `LoggerInterface` (default `FileLogger`).          |
| Fallbacks          | Assets → Fallback → 404 chain.                                  |

---

## See Also

- [`Request`](./Request.md)
- [`Response`](./Response.md)
- [`Assets`](./Assets.md)
- [`Session`](./Session.md)
- [`Logging\LoggerInterface`](./Logging/LoggerInterface.md)
