# Middleware

Middleware in Erlenmeyer provides a clean way to **intercept, modify, or reject a request** before it reaches your route handler — or to alter the response afterward.

They’re simple **callables** that receive the current `Request`, `Response`, a `$next` callback, and optional route parameters.

---

## How a middleware works

A middleware has the following signature:

```php
function (Request $req, Response $res, callable $next, stdClass $params): void
```

It can:

- inspect or modify the incoming request,
- stop execution and send a response directly,
- or call `$next()` to continue the chain.

Example:

```php
$auth = function ($req, $res, $next, $params) {
    $token = $req->getQueryParam('token');
    if ($token !== 'secret') {
        $res->withText('Unauthorized')->setStatusCode(401)->send();
        return;
    }

    $next($req, $res, $params);
};
```

---

## Applying middleware to a single route

You can attach one or more middlewares directly to a route:

```php
$app->get('/dashboard', function ($req, $res) {
    $res->withText('Welcome to your dashboard')->send();
}, [$auth]);
```

Middlewares are executed **in the order they’re defined**.

---

## Global middlewares

If you want a middleware to run on **every route**, register it globally:

```php
$app->addMiddleware($auth);
```

All routes declared afterward will automatically include it in their execution chain.
You can combine global and route-specific middlewares freely.

---

## Stopping the chain

A middleware can terminate the request early simply by **not calling `$next()`**.
This is useful for authentication, validation, or rate-limiting:

```php
$rateLimit = function ($req, $res, $next, $params) {
    if (Session::get('requests') > 100) {
        $res->setStatusCode(429)->withText('Too many requests')->send();
        return;
    }
    $next($req, $res, $params);
};
```

---

## Execution order

Erlenmeyer builds the middleware chain in a way that ensures  
the **first added middleware is the first executed**.

Internally, the framework reverses the array before wrapping each middleware,  
so execution happens in the same order you register them — from outside in.

For example:

```php
$app->addMiddleware(A);
$app->addMiddleware(B);
$app->addMiddleware(C);
```

The resulting execution order is:

```
A → B → C → Route Handler
```

Each middleware receives a `$next()` callback that continues the chain.
If a middleware doesn’t call `$next()`, the chain stops there.

---

### Route-specific middlewares

When you attach middlewares to a specific route:

```php
$app->get('/admin', $handler, [$auth, $log]);
```

The order will be:

```
auth → log → Route Handler
```

---

### Global + route middlewares together

Global middlewares run before route-specific ones:

```php
$app->addMiddleware($log);
$app->get('/secure', $handler, [$auth]);
```

Execution order:

```
Global log → Route auth → Route Handler
```

---

### Visual model

```
+-----------------------------+
| Global Middleware (A)       |
|   +-----------------------+ |
|   | Route Middleware (B)  | |
|   |   +-----------------+ | |
|   |   | Handler (C)     | | |
|   |   +-----------------+ | |
|   +-----------------------+ |
+-----------------------------+
```

→ Executed in order: **A → B → C**

---

## Example: chaining multiple middlewares

```php
$log = fn($req, $res, $next) => (
    error_log("[LOG] {$req->getMethod()} {$req->getUri()}"),
    $next($req, $res, new stdClass())
);

$auth = fn($req, $res, $next) => (
    $req->getQueryParam('token') === 'secret'
        ? $next($req, $res, new stdClass())
        : $res->withText('Unauthorized')->setStatusCode(401)->send()
);

$app->addMiddleware($log);
$app->get('/admin', fn($req, $res) => $res->withText('Admin OK')->send(), [$auth]);
```

**Execution flow:**

1. `$log` runs (global)
2. `$auth` runs (route-specific)
3. Route handler runs
4. Response is sent

---

## Middleware use cases

✅ Common scenarios:

- Authentication / Authorization
- Logging and metrics
- Request validation
- CORS and headers
- JSON body parsing or content negotiation
- Exception wrapping or retry mechanisms

Because middlewares are just **PHP functions**, you can easily reuse or compose them across projects.

---

## Internals

When you define a route, Erlenmeyer merges:

- Global middlewares (added with `addMiddleware()`)
- Route-specific ones (passed to `get()`, `post()`, etc.)

Then it wraps them around the route’s handler using `applyMiddlewares()` inside the `App` class:

```php
private function applyMiddlewares(callable $handler, array $middlewares): callable {
    $next = $handler;
    $middlewares = array_reverse($middlewares);

    foreach ($middlewares as $middleware) {
        $next = function ($req, $res, $params) use ($middleware, $next) {
            $middleware($req, $res, fn($req, $res, $p) => $next($req, $res, $p), $params);
        };
    }

    return $next;
}
```

This keeps everything explicit, transparent, and framework-free — just **function composition**.

---

## In short

| Concept               | Description                                                      |
| --------------------- | ---------------------------------------------------------------- |
| **Middleware**        | A function that can inspect, modify, or short-circuit a request. |
| **Global middleware** | Applied to every route.                                          |
| **Route middleware**  | Applied only to specific routes.                                 |
| **$next()**           | Calls the next middleware or the final route handler.            |
| **Freedom**           | You can stack them however you want.                             |
