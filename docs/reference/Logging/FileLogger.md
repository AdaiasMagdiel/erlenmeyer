# Class: `AdaiasMagdiel\Erlenmeyer\Logging\FileLogger`

**Namespace:** `AdaiasMagdiel\Erlenmeyer\Logging`  
**Implements:** [`LoggerInterface`](./LoggerInterface.md)  
**Defined in:** `app/Logging/FileLogger.php`

---

## Overview

The `FileLogger` writes structured log entries to disk.  
It supports **log rotation**, **retention limits**, and contextual **exception logging**.  
Each log entry includes a timestamp, level, and message.

---

## Constants

| Name            | Value             | Description                                   |
| --------------- | ----------------- | --------------------------------------------- |
| `MAX_LOG_SIZE`  | `3 * 1024 * 1024` | Maximum log file size before rotation (3 MB). |
| `MAX_LOG_FILES` | `5`               | Maximum number of rotated log files retained. |

---

## Properties

| Name       | Visibility | Type      | Description                                                   |
| ---------- | ---------- | --------- | ------------------------------------------------------------- |
| `$logDir`  | private    | `?string` | Directory where log files are stored (or `null` if disabled). |
| `$logFile` | private    | `string`  | Full path to the active log file.                             |

---

## Constructor

### `__construct(string $logDir = '')`

Creates a new `FileLogger` instance.

| Parameter | Type     | Description                                         |
| --------- | -------- | --------------------------------------------------- |
| `$logDir` | `string` | Directory for storing logs. Empty disables logging. |

**Behavior:**

- Automatically creates the directory if missing.
- Defaults to writing logs to `info.log` in the provided directory.

---

## Methods

### `log(LogLevel $level = LogLevel::INFO, string $message = ''): void`

Writes a formatted log entry to the active file.

| Parameter  | Type       | Description         |
| ---------- | ---------- | ------------------- |
| `$level`   | `LogLevel` | Log severity level. |
| `$message` | `string`   | Message content.    |

**Returns:** `void`  
**Notes:**

- Automatically rotates when file size exceeds `MAX_LOG_SIZE`.
- Skips logging if `$logDir` is `null`.

---

### `logException(Exception $exception, ?Request $request = null): void`

Logs detailed exception information.

| Parameter    | Type        | Description                    |
| ------------ | ----------- | ------------------------------ |
| `$exception` | `Exception` | Exception to log.              |
| `$request`   | `?Request`  | Optional HTTP request context. |

**Returns:** `void`

---

### `rotateLogFile(): void` _(private)_

Handles file rotation when size exceeds `MAX_LOG_SIZE`.

**Behavior:**

- Renames old logs (e.g., `info.log.1 → info.log.2`).
- Keeps up to `MAX_LOG_FILES` rotated versions.
- Writes `"Log file rotated."` to the new file.

---

## Behavior Summary

| Feature                   | Description                                        |
| ------------------------- | -------------------------------------------------- |
| **File Rotation**         | Automatically renames logs when exceeding 3 MB.    |
| **Retention**             | Keeps up to 5 historical log files.                |
| **Formatting**            | Standard `[timestamp] [LEVEL] message` format.     |
| **Contextual Exceptions** | Includes stack trace and optional request data.    |
| **Silent Disable**        | Logging disabled if `$logDir` is empty or invalid. |

---

## See Also

- [`ConsoleLogger`](./ConsoleLogger.md)
- [`LoggerInterface`](./LoggerInterface.md)
- [`LogLevel`](./LogLevel.md)
