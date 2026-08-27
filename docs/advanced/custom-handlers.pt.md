# Advanced: Custom Handlers

!!! note "Tradução em andamento"
    Esta página ainda não foi traduzida para pt-BR e está exibindo o conteúdo original em inglês. [Ajude a traduzir](https://github.com/AdaiasMagdiel/Erlenmeyer).


Erlenmeyer allows you to fully customize **exception handling** behavior via
`App::setExceptionHandler()`.

This is useful when integrating Erlenmeyer with external systems (such as Sentry, Logstash, or Graylog), or when defining precise responses for specific error types.

---

## 1. Registering Custom Exception Handlers

The method `setExceptionHandler()` lets you define specific behaviors for particular exception types.

```php
$app->setExceptionHandler(TypeError::class, function ($req, $res, $e) {
    $res->setStatusCode(400)
        ->withJson([
            'error' => 'Invalid type',
            'message' => $e->getMessage(),
        ])
        ->send();
});
```

You can also handle your own custom exception classes:

```php
class ValidationException extends Exception {}

$app->setExceptionHandler(ValidationException::class, function ($req, $res, $e) {
    $res->setStatusCode(422)
        ->withJson(['error' => $e->getMessage()])
        ->send();
});
```

When an exception is thrown, Erlenmeyer traverses the exception’s class hierarchy to find the **most specific** registered handler, falling back to the generic `Throwable` handler if none matches.

---

## 2. Global (Fallback) Exception Handler

By default, Erlenmeyer defines a generic 500 handler:

```php
$app->setExceptionHandler(Throwable::class, function ($req, $res, $e) {
    $res->setStatusCode(500)
        ->withHtml("<h1>500 Internal Server Error</h1><p>Error: {$e->getMessage()}</p>")
        ->send();
});
```

You can override it to return a consistent JSON response instead:

```php
$app->setExceptionHandler(Throwable::class, function ($req, $res, $e) {
    $res->setStatusCode(500)
        ->withJson([
            'status' => 'error',
            'message' => $e->getMessage(),
        ])
        ->send();
});
```

---

## 3. Recording Errors Externally

Erlenmeyer has no built-in logging system — it stays out of the way so you can wire up
whatever observability tool you already use (Monolog, Sentry, a plain file, etc.) directly
inside your handlers.

```php
function recordError(Throwable $e, $req): void
{
    $entry = [
        'timestamp' => date('c'),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'request' => [
            'method' => $req->getMethod(),
            'uri' => $req->getUri(),
        ],
    ];

    file_put_contents(__DIR__ . '/logs/errors.jsonl', json_encode($entry) . PHP_EOL, FILE_APPEND);
}

$app->setExceptionHandler(RuntimeException::class, function ($req, $res, $e) {
    recordError($e, $req);

    $res->setStatusCode(500)
        ->withJson(['error' => 'Unexpected server error'])
        ->send();
});
```

---

## 4. Full Example

```php
use AdaiasMagdiel\Erlenmeyer\App;

require 'vendor/autoload.php';

$app = new App();

// Handler for validation exceptions
$app->setExceptionHandler(ValidationException::class, function ($req, $res, $e) {
    recordError($e, $req);
    $res->setStatusCode(422)->withJson(['error' => $e->getMessage()])->send();
});

// Global fallback handler
$app->setExceptionHandler(Throwable::class, function ($req, $res, $e) {
    recordError($e, $req);
    $res->setStatusCode(500)->withText('Internal Server Error')->send();
});

$app->get('/test', function ($req, $res) {
    throw new ValidationException('Invalid input data');
});

$app->run();
```

---

## 5. Best Practices

✅ **Catch specific exception types first** (e.g. `ValidationException`, `TypeError`).
✅ **Record technical detail externally**, and keep handlers focused on user-facing messages.
✅ **Avoid exposing sensitive data** in production error responses.
✅ **Combine with global middlewares** to normalize errors consistently.

---

## Summary

| Feature                    | Purpose                                                |
| --------------------------- | ------------------------------------------------------ |
| **setExceptionHandler()**   | Associates exception types with custom responses       |
| **External recording**      | Integrate with external tools (Sentry, Logstash, etc.) |

With these tools, you can build professional-grade error handling and observability pipelines inside Erlenmeyer.

---

Next:
📘 [Testing with ErlenClient →](../reference/Testing/ErlenClient.md)
