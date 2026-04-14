# Class: `AdaiasMagdiel\Erlenmeyer\Logging\NullLogger`

**Namespace:** `AdaiasMagdiel\Erlenmeyer\Logging`  
**Implements:** [`LoggerInterface`](./LoggerInterface.md)  
**Defined in:** `app/Logging/NullLogger.php`

---

## Overview

The `NullLogger` is a **Null Object** implementation of `LoggerInterface`.

It silently discards all log messages and exceptions — no files written, no output, no side effects.
It is the **default logger** used by `App` when no logger is explicitly provided.

```php
// These two are equivalent:
$app = new App();
$app = new App(new NullLogger());
```

---

## When to Use It

| Scenario                                    | Rationale                                                  |
| ------------------------------------------- | ---------------------------------------------------------- |
| Development with external log aggregator    | Let the external system (Sentry, Datadog, etc.) handle it  |
| Stateless APIs or CLI scripts               | No disk writes, no overhead                                |
| Testing                                     | Avoid log noise in test output                             |
| Explicitly disabling all internal logging   | Cleaner than passing `null` to loggers that check for it   |

---

## Methods

### `log(LogLevel $level, string $message): void`

Discards the log message. Performs no action.

| Parameter  | Type       | Description              |
| ---------- | ---------- | ------------------------ |
| `$level`   | `LogLevel` | Severity level (ignored) |
| `$message` | `string`   | Message content (ignored)|

**Returns:** `void`

---

### `logException(Throwable $e, ?Request $request = null): void`

Discards the exception details. Performs no action.

| Parameter  | Type        | Description                   |
| ---------- | ----------- | ----------------------------- |
| `$e`       | `Throwable` | Exception instance (ignored)  |
| `$request` | `?Request`  | Request context (ignored)     |

**Returns:** `void`

---

## Behavior Summary

| Feature            | Description                                    |
| ------------------ | ---------------------------------------------- |
| **Output**         | None — all calls are silently discarded        |
| **Side effects**   | None — no files, no network, no memory         |
| **Exceptions**     | Never throws                                   |
| **Default logger** | Used by `App` when no logger is passed         |

---

## See Also

- [`LoggerInterface`](./LoggerInterface.md)
- [`FileLogger`](./FileLogger.md)
- [`ConsoleLogger`](./ConsoleLogger.md)
- [`App`](../App.md)
