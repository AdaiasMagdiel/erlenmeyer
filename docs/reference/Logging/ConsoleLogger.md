# Class: `AdaiasMagdiel\Erlenmeyer\Logging\ConsoleLogger`

**Namespace:** `AdaiasMagdiel\Erlenmeyer\Logging`  
**Implements:** [`LoggerInterface`](./LoggerInterface.md)  
**Defined in:** `app/Logging/ConsoleLogger.php`

---

## Overview

The `ConsoleLogger` writes formatted log entries to the PHP error log (stderr).  
It supports all standard log levels and allows excluding specific levels from output.

It also provides detailed exception logging, including stack traces and optional  
request context information.

---

## Properties

| Name                | Visibility | Type         | Description                   |
| ------------------- | ---------- | ------------ | ----------------------------- |
| `excludedLogLevels` | public     | `LogLevel[]` | List of log levels to ignore. |

---

## Constants

| Name               | Value           | Description                               |
| ------------------ | --------------- | ----------------------------------------- |
| `TIMESTAMP_FORMAT` | `'Y-m-d H:i:s'` | Date/time format used in all log entries. |

---

## Constructor

### `__construct(array $excludedLogLevels = [])`

Creates a new `ConsoleLogger` instance.

| Parameter            | Type         | Description                              |
| -------------------- | ------------ | ---------------------------------------- |
| `$excludedLogLevels` | `LogLevel[]` | Array of levels to exclude from logging. |

**Throws:** `InvalidArgumentException` – If any element is not a `LogLevel` instance.

---

## Methods

### `log(LogLevel $level = LogLevel::INFO, string $message = ''): void`

Logs a message to the PHP error log.

| Parameter  | Type       | Description                         |
| ---------- | ---------- | ----------------------------------- |
| `$level`   | `LogLevel` | Log severity level.                 |
| `$message` | `string`   | Message to log (must not be empty). |

**Returns:** `void`  
**Throws:** `InvalidArgumentException` – If the message is empty.

---

### `logException(Exception $exception, ?Request $request = null): void`

Logs an exception with contextual information.

| Parameter    | Type        | Description                              |
| ------------ | ----------- | ---------------------------------------- |
| `$exception` | `Exception` | Exception to log.                        |
| `$request`   | `?Request`  | Optional request for additional context. |

**Returns:** `void`

---

## Behavior Summary

| Feature               | Description                                                      |
| --------------------- | ---------------------------------------------------------------- |
| **Destination**       | Writes logs to PHP error log (`error_log()`) or stderr fallback. |
| **Timestamping**      | All entries are timestamped using `Y-m-d H:i:s`.                 |
| **Exclusion List**    | Supports filtering specific log levels.                          |
| **Exception Logging** | Includes file, line, message, stack trace, and request data.     |
| **Security**          | Escapes messages to prevent log injection.                       |

---

## See Also

- [`FileLogger`](./FileLogger.md)
- [`LoggerInterface`](./LoggerInterface.md)
- [`LogLevel`](./LogLevel.md)
