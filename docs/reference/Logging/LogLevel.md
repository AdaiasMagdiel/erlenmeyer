# Enum: `AdaiasMagdiel\Erlenmeyer\Logging\LogLevel`

**Namespace:** `AdaiasMagdiel\Erlenmeyer\Logging`  
**Defined in:** `app/Logging/LogLevel.php`

---

## Overview

Represents the severity of a log message.  
Each log level indicates the urgency or criticality of a logged event.

---

## Enum Cases

| Case       | Value        | Description                                                  |
| ---------- | ------------ | ------------------------------------------------------------ |
| `DEBUG`    | `'DEBUG'`    | Low-level system information for debugging purposes.         |
| `INFO`     | `'INFO'`     | General operational messages that highlight progress.        |
| `WARNING`  | `'WARNING'`  | Indications of potential issues or non-critical errors.      |
| `ERROR`    | `'ERROR'`    | Runtime errors or unexpected conditions requiring attention. |
| `CRITICAL` | `'CRITICAL'` | Serious failures that require immediate investigation.       |

---

## Usage Example

```php
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;

$level = LogLevel::ERROR;
echo $level->value; // "ERROR"
```
