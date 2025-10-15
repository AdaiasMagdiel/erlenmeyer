# Class: `AdaiasMagdiel\Erlenmeyer\Session`

**Namespace:** `AdaiasMagdiel\Erlenmeyer`  
**Defined in:** `app/Session.php`

---

## Overview

The `Session` class provides static helper methods for managing PHP session data  
and **flash messages** in a structured and predictable way.

It abstracts the native `$_SESSION` superglobal, automatically ensures  
the session is started when needed, and supports temporary flash values  
that persist for a single request.

---

## Behavior Summary

| Feature                      | Description                                                           |
| ---------------------------- | --------------------------------------------------------------------- |
| **Automatic Initialization** | Automatically starts the session when needed.                         |
| **Key/Value Storage**        | Provides `get`, `set`, `has`, and `remove` for standard session keys. |
| **Flash Messages**           | Supports temporary values that are cleared after retrieval.           |
| **Validation**               | Prevents usage of empty session keys.                                 |
| **Stateless Access**         | Static interface — no instantiation required.                         |

---

## Private Methods

### `ensureSessionStarted(): void`

Ensures that a PHP session is active.  
If no session is started, it calls `session_start()`.

**Returns:** `void`

---

## Public Static Methods

### `get(string $key, mixed $default = null): mixed`

Retrieves a session value by key.

| Parameter  | Type     | Description                                |
| ---------- | -------- | ------------------------------------------ |
| `$key`     | `string` | The session key.                           |
| `$default` | `mixed`  | Value to return if the key does not exist. |

**Returns:** `mixed` – The stored value or the default.

---

### `set(string $key, mixed $value): void`

Stores a value in the session.

| Parameter | Type     | Description     |
| --------- | -------- | --------------- |
| `$key`    | `string` | Session key.    |
| `$value`  | `mixed`  | Value to store. |

**Returns:** `void`  
**Throws:** `InvalidArgumentException` – If the key is empty.

---

### `has(string $key): bool`

Checks whether a session key exists.

| Parameter | Type     | Description           |
| --------- | -------- | --------------------- |
| `$key`    | `string` | Session key to check. |

**Returns:** `bool` – `true` if the key exists, otherwise `false`.

---

### `remove(string $key): void`

Removes a key (and its value) from the session.

| Parameter | Type     | Description    |
| --------- | -------- | -------------- |
| `$key`    | `string` | Key to remove. |

**Returns:** `void`

---

### `flash(string $key, mixed $value): void`

Sets a **flash message** available for the **next request only**.  
Flash data is automatically deleted after retrieval.

| Parameter | Type     | Description          |
| --------- | -------- | -------------------- |
| `$key`    | `string` | Flash message key.   |
| `$value`  | `mixed`  | Flash message value. |

**Returns:** `void`  
**Throws:** `InvalidArgumentException` – If the key is empty.

---

### `getFlash(string $key, mixed $default = null): mixed`

Retrieves and removes a flash message from the session.  
Once retrieved, the flash value is deleted immediately.

| Parameter  | Type     | Description                 |
| ---------- | -------- | --------------------------- |
| `$key`     | `string` | Flash key.                  |
| `$default` | `mixed`  | Default value if not found. |

**Returns:** `mixed` – The flash message value or default.

---

### `hasFlash(string $key): bool`

Checks whether a flash message exists.

| Parameter | Type     | Description         |
| --------- | -------- | ------------------- |
| `$key`    | `string` | Flash key to check. |

**Returns:** `bool` – `true` if the flash message exists, otherwise `false`.

---

## Usage Notes

- Sessions are started lazily — the first call to any method triggers `session_start()` if necessary.
- Flash messages are stored in the `$_SESSION['flash']` array internally.
- After retrieving a flash message, it is immediately removed from the session.

---

## See Also

- [`App`](./App.md)
- [`Request`](./Request.md)
- [`Response`](./Response.md)
