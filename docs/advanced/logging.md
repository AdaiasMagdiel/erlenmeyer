# Advanced: Logging

Erlenmeyer includes a flexible logging system designed for both **file-based** and **console-based** environments.  
It provides standardized severity levels and supports context-aware exception logging, including request details and stack traces.

---

## Overview

Logging in Erlenmeyer is powered by the `LoggerInterface`, which defines two main methods:

```php
public function log(LogLevel $level, string $message): void;
public function logException(Exception $e, ?Request $request = null): void;
```

Two implementations are included by default:

| Class                                                    | Description                                                                |
| -------------------------------------------------------- | -------------------------------------------------------------------------- |
| [`FileLogger`](../reference/Logging/FileLogger.md)       | Writes structured log entries to disk, with automatic rotation.            |
| [`ConsoleLogger`](../reference/Logging/ConsoleLogger.md) | Writes log entries to the PHP error log or STDERR (useful in CLI/testing). |

You can also create **custom loggers** by implementing `LoggerInterface` — covered in the [Custom Handlers](./custom-handlers.md) section.

---

## Log Levels

Erlenmeyer defines standard log severity levels using the `LogLevel` enum:

```php
enum LogLevel: string {
    case DEBUG = 'DEBUG';
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case CRITICAL = 'CRITICAL';
}
```

All loggers support these levels consistently.

---

## 1. FileLogger

### Basic Setup

The `FileLogger` stores logs in a directory of your choice and automatically rotates log files when they reach **3MB**.

```php
use AdaiasMagdiel\Erlenmeyer\Logging\FileLogger;
use AdaiasMagdiel\Erlenmeyer\App;

$logger = new FileLogger(__DIR__ . '/logs');
$app = new App(null, $logger);
```

- Logs are stored in `info.log` inside the specified directory.
- When `info.log` exceeds 3MB, it is renamed to `info.log.1`, and older logs are shifted up to `info.log.5`.

### Example Log Entry

```
[2025-10-15 14:03:21] [INFO] Route registered: GET /users
```

### Exception Logging

Exceptions are logged with full context, including the request method, URI, and stack trace:

```
[2025-10-15 14:05:11] [ERROR] Invalid argument in /app/UserController.php:84
Request: POST /users
#0 /app/App.php(210): ...
```

---

## 2. ConsoleLogger

The `ConsoleLogger` writes directly to PHP’s **error log** or `STDERR`.
This is ideal for development, testing, or containerized deployments.

```php
use AdaiasMagdiel\Erlenmeyer\Logging\ConsoleLogger;

$logger = new ConsoleLogger();
$app = new App(null, $logger);
```

You can also exclude specific levels to reduce verbosity:

```php
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;

$logger = new ConsoleLogger([LogLevel::DEBUG, LogLevel::INFO]);
```

Now only warnings, errors, and critical messages will appear in your logs.

---

## 3. Custom Logger Injection

Any logger implementing `LoggerInterface` can be injected into the application.

```php
use AdaiasMagdiel\Erlenmeyer\App;
use MyApp\CustomLogger;

$logger = new CustomLogger();
$app = new App(null, $logger);
```

The logger is used internally by the `App` to record:

- Route registration and middleware execution
- 404 and 500 errors
- Unhandled exceptions
- Asset serving and redirect handling

---

## 4. Handling Exceptions Manually

You can also log exceptions directly in your route handlers or middlewares:

```php
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;

$app->get('/users', function ($req, $res) use ($app) {
    try {
        // ...
    } catch (Exception $e) {
        $appLogger = new FileLogger(__DIR__ . '/logs');
        $appLogger->logException($e, $req);
        $res->setStatusCode(500)->withText('An internal error occurred');
    }
});
```

---

## 5. When Logging is Disabled

If you initialize `FileLogger` without a directory path:

```php
$logger = new FileLogger('');
```

Logging is silently disabled — no files are created, and all log calls become no-ops.

This can be useful for production builds where logs are handled by external systems.

---

## Summary

| Logger            | Output                 | Rotation | Contextual Exceptions | Recommended For  |
| ----------------- | ---------------------- | -------- | --------------------- | ---------------- |
| **FileLogger**    | Files (`info.log`)     | ✅ Yes   | ✅ Yes                | Production       |
| **ConsoleLogger** | `error_log()` / STDERR | ❌ No    | ✅ Yes                | Development / CI |
| **Custom Logger** | User-defined           | Optional | Optional              | Advanced setups  |

---

Next:
📘 [Custom Handlers →](./custom-handlers.md)
