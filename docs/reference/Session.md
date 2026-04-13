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
| **Session Fixation Guard**   | `regenerate()` replaces the session ID after privilege changes.       |
| **Lock Release**             | `close()` calls `session_write_close()` to unblock concurrent requests. |
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

### `regenerate(bool $deleteOldSession = true): void`

Regenerates the session ID to prevent **session fixation** attacks.

Call this immediately after any change in privilege level — for example, when a user
logs in or logs out.

| Parameter           | Type   | Description                                                  |
| ------------------- | ------ | ------------------------------------------------------------ |
| `$deleteOldSession` | `bool` | Whether to delete the old session file. Defaults to `true`.  |

**Returns:** `void`

**Security Note:** Always call `Session::regenerate()` after successful authentication.
If the session ID is not regenerated on login, an attacker who obtained the pre-login
session ID can hijack the authenticated session (session fixation).

**Example:**

```php
$app->post('/login', function (Request $req, Response $res, $params) {
    $user = authenticateUser(
        $req->getFormDataParam('email'),
        $req->getFormDataParam('password')
    );

    if ($user) {
        Session::regenerate(); // Invalidate the pre-login session ID
        Session::set('user_id', $user['id']);
        $res->redirect('/dashboard')->send();
    } else {
        Session::flash('error', 'Invalid credentials');
        $res->redirect('/login')->send();
    }
});
```

---

### `close(): void`

Writes session data and releases the session lock via `session_write_close()`.

Call this as soon as you are done writing to the session — particularly before
long-running operations or parallel AJAX requests. PHP holds a session file lock
for the entire request duration by default, which causes concurrent requests from
the same user to queue up.

**Returns:** `void`

**Note:** Only acts if the session is currently active. Safe to call even if the
session was already closed.

**Example — Release lock before a slow operation:**

```php
$app->get('/report', function (Request $req, Response $res, $params) {
    $userId = Session::get('user_id');

    // Done reading/writing session — release the lock
    Session::close();

    // This long operation no longer blocks concurrent tabs
    $report = generateLargeReport($userId);

    $res->withJson($report)->send();
});
```

**Example — AJAX-friendly session handling:**

```php
$app->post('/track', function (Request $req, Response $res, $params) {
    Session::set('last_action', time());
    Session::close(); // Allow concurrent AJAX calls to proceed immediately

    $res->withJson(['ok' => true])->send();
});
```

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
- Call `Session::regenerate()` immediately after any login or privilege escalation to prevent session fixation attacks.
- Call `Session::close()` before long operations or parallel AJAX calls to release the session file lock and avoid request queuing.

---

## See Also

- [`App`](./App.md)
- [`Request`](./Request.md)
- [`Response`](./Response.md)
