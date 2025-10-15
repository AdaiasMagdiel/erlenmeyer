# Advanced: Custom Handlers

Erlenmeyer allows you to fully customize **logging** and **exception handling** behavior.  
This includes:

- Creating **custom loggers** by implementing `LoggerInterface`;
- Registering **custom exception handlers** using `App::setExceptionHandler()`.

These features are useful when integrating Erlenmeyer with external systems (such as Sentry, Logstash, or Graylog), or when defining precise responses for specific error types.

---

## 1. Creating a Custom Logger

All loggers in Erlenmeyer implement the [`LoggerInterface`](../reference/Logging/LoggerInterface.md):

```php
interface LoggerInterface
{
    public function log(LogLevel $level, string $message): void;
    public function logException(Exception $e, ?Request $request = null): void;
}
```

To create your own logger, simply implement this interface.

### Example: JSON-based Logger

```php
use AdaiasMagdiel\Erlenmeyer\Logging\LoggerInterface;
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;
use AdaiasMagdiel\Erlenmeyer\Request;
use Exception;

class JsonLogger implements LoggerInterface
{
    private string $file;

    public function __construct(string $file = __DIR__ . '/app.log')
    {
        $this->file = $file;
    }

    public function log(LogLevel $level, string $message): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => $level->value,
            'message' => $message
        ];

        file_put_contents($this->file, json_encode($entry) . PHP_EOL, FILE_APPEND);
    }

    public function logException(Exception $e, ?Request $request = null): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => LogLevel::ERROR->value,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
            'request' => $request ? [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
            ] : null,
        ];

        file_put_contents($this->file, json_encode($entry) . PHP_EOL, FILE_APPEND);
    }
}
```

Then inject it into your app:

```php
use AdaiasMagdiel\Erlenmeyer\App;

$app = new App(null, new JsonLogger(__DIR__ . '/logs/app.jsonl'));
```

Each log entry is stored as a separate JSON line — ideal for structured logging and observability tools.

---

## 2. Registering Custom Exception Handlers

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

## 3. Global (Fallback) Exception Handler

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

## 4. Combining Loggers and Handlers

It’s common to use a logger inside a custom exception handler for better traceability:

```php
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;

$app->setExceptionHandler(RuntimeException::class, function ($req, $res, $e) use ($app) {
    // Log details
    $logger = new JsonLogger(__DIR__ . '/logs/errors.jsonl');
    $logger->logException($e, $req);

    // Send friendly response
    $res->setStatusCode(500)
        ->withJson(['error' => 'Unexpected server error'])
        ->send();
});
```

---

## 5. Full Example

```php
use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Logging\FileLogger;

require 'vendor/autoload.php';

$logger = new FileLogger(__DIR__ . '/logs');
$app = new App(null, $logger);

// Handler for validation exceptions
$app->setExceptionHandler(ValidationException::class, function ($req, $res, $e) use ($logger) {
    $logger->logException($e, $req);
    $res->setStatusCode(422)->withJson(['error' => $e->getMessage()])->send();
});

// Global fallback handler
$app->setExceptionHandler(Throwable::class, function ($req, $res, $e) use ($logger) {
    $logger->logException($e, $req);
    $res->setStatusCode(500)->withText('Internal Server Error')->send();
});

$app->get('/test', function ($req, $res) {
    throw new ValidationException('Invalid input data');
});

$app->run();
```

---

## 6. Best Practices

✅ **Catch specific exception types first** (e.g. `ValidationException`, `TypeError`).
✅ **Use loggers for technical detail**, and handlers for user-facing messages.
✅ **Avoid exposing sensitive data** in production error responses.
✅ **Combine with global middlewares** to normalize errors consistently.

---

## Summary

| Feature                        | Purpose                                                |
| ------------------------------ | ------------------------------------------------------ |
| **LoggerInterface**            | Defines the common logging contract                    |
| **FileLogger / ConsoleLogger** | Default logger implementations                         |
| **setExceptionHandler()**      | Associates exception types with custom responses       |
| **Custom Logger**              | Integrate with external tools (Sentry, Logstash, etc.) |

With these tools, you can build professional-grade error handling and observability pipelines inside Erlenmeyer.

---

Next:
📘 [Testing with ErlenClient →](../reference/Testing/ErlenClient.md)
