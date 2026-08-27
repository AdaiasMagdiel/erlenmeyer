# Error Handling

Erlenmeyer provides a simple yet powerful mechanism for handling **errors and exceptions** —  
from route-level issues to application-wide exception mapping.

You can define **custom exception handlers**, **global fallbacks**, and **graceful 404 pages**,  
all while keeping things clear and explicit.

---

## 🧩 Default Behavior

By default, Erlenmeyer catches all uncaught exceptions and displays a simple HTML error page:

```html
<h1>500 Internal Server Error</h1>
<p>Error: [message]</p>
```

If no route matches the request, the framework automatically runs the **404 handler** —
which is also customizable.

---

## ⚙️ Custom 404 Handler

You can override the default 404 handler using `set404Handler()`:

```php
$app->set404Handler(function (Request $req, Response $res) {
    $res->setStatusCode(404)
        ->withHtml('<h1>Oops! Page not found 😕</h1>')
        ->send();
});
```

This handler is called when **no route matches** and **no static asset** is served.

You can include route info, headers, or even return JSON for API responses.

```php
$app->set404Handler(fn($req, $res) =>
    $res->withJson([
        'error' => 'Resource not found',
        'path' => $req->getUri()
    ])->setStatusCode(404)->send()
);
```

---

## 🧱 Exception Handling

Erlenmeyer lets you **map specific exception classes** to custom handlers using
`setExceptionHandler($class, $callable)`.

Each handler receives the current `Request`, `Response`, and the thrown exception instance.

Example:

```php
use RuntimeException;
use TypeError;

$app->setExceptionHandler(RuntimeException::class, function ($req, $res, $e) {
    $res->withJson([
        'error' => 'Runtime error',
        'message' => $e->getMessage(),
    ])->setStatusCode(500)->send();
});

$app->setExceptionHandler(TypeError::class, function ($req, $res, $e) {
    $res->withText("Invalid type: {$e->getMessage()}")
        ->setStatusCode(400)
        ->send();
});
```

If an exception doesn’t match any registered class,
Erlenmeyer falls back to the **generic `Throwable` handler** (the default 500 page).

---

## 🧰 Handling PHP Errors

Erlenmeyer automatically converts PHP errors (like notices or warnings)
into `ErrorException` instances, which are then caught by the exception system.

This ensures **consistent error handling** across your entire app.

Example:

```php
// Triggers an ErrorException (not a fatal PHP warning)
echo $undefinedVar;
```

→ The exception is caught, logged, and rendered using your registered handlers.

---

## 🔄 Exception Resolution Order

When an exception is thrown, Erlenmeyer searches for a handler in this order:

1. Exact match (e.g., `TypeError`)
2. Parent class (e.g., `Error`)
3. Generic `Throwable` handler (fallback)

This means you can safely define **specific handlers first**
and let more general ones handle everything else.

---

## 🚀 Example: Full Error Stack

```php
$app->setExceptionHandler(DomainException::class, fn($req, $res, $e) =>
    $res->withJson([
        'error' => 'Domain violation',
        'message' => $e->getMessage()
    ])->setStatusCode(422)->send()
);

$app->setExceptionHandler(Throwable::class, fn($req, $res, $e) =>
    $res->withJson([
        'error' => 'Unexpected server error',
        'message' => $e->getMessage()
    ])->setStatusCode(500)->send()
);
```

Then, if your route throws:

```php
throw new DomainException("Invalid email format");
```

You’ll get:

```json
{
  "error": "Domain violation",
  "message": "Invalid email format"
}
```

---

## 🧭 Summary

| Concept               | Description                                 |
| --------------------- | ------------------------------------------- |
| **404 Handler**       | Custom response when no route matches       |
| **Exception Handler** | Maps exception classes to custom handlers   |
| **Error Conversion**  | PHP warnings and notices become exceptions  |
| **Fallback**          | Default 500 handler for uncaught exceptions |

---

!!! tip "Keep it clean"
	Because handlers receive both the `Request` and `Response`,
	you can return HTML for web apps, JSON for APIs,
	or even integrate third-party logging and monitoring tools like Sentry or Bugsnag.
