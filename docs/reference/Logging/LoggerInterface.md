# Interface: `AdaiasMagdiel\Erlenmeyer\Logging\LoggerInterface`

**Namespace:** `AdaiasMagdiel\Erlenmeyer\Logging`  
**Defined in:** `app/Logging/LoggerInterface.php`

---

## Overview

Defines the contract for logging messages and exceptions in Erlenmeyer.

Implementations of this interface provide consistent logging behavior,  
allowing the application to write logs to various destinations (console, file, etc.)  
using standardized severity levels.

---

## Methods

### `log(LogLevel $level, string $message): void`

Writes a log entry with the specified severity level.

| Parameter  | Type       | Description                        |
| ---------- | ---------- | ---------------------------------- |
| `$level`   | `LogLevel` | Severity level of the log message. |
| `$message` | `string`   | Message text to log.               |

**Returns:** `void`

---

### `logException(Exception $e, ?Request $request = null): void`

Logs an exception, optionally including HTTP request context.

| Parameter  | Type        | Description                                             |
| ---------- | ----------- | ------------------------------------------------------- |
| `$e`       | `Exception` | Exception instance to log.                              |
| `$request` | `?Request`  | Optional request providing context (method, URI, etc.). |

**Returns:** `void`

---

## Implementations

- [`ConsoleLogger`](./ConsoleLogger.md)
- [`FileLogger`](./FileLogger.md)
