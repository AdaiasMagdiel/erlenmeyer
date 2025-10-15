# Sessions

Erlenmeyer provides a simple, static `Session` class for working with **PHP sessions**  
in a clean and expressive way — no need to deal with `$_SESSION` directly.

It supports regular session data and one-time **flash messages**,  
making it ideal for login states, form feedback, or transient notifications.

---

## 🧩 Overview

The `Session` class wraps PHP’s native session API and ensures that a session is  
always started automatically when needed.

You can store, retrieve, check, and remove session data with clear, predictable methods.

Example:

```php
use AdaiasMagdiel\Erlenmeyer\Session;

// Store user data
Session::set('user_id', 42);

// Retrieve it later
$userId = Session::get('user_id');

// Check existence
if (Session::has('user_id')) {
    echo "User logged in";
}
```

---

## ⚙️ Starting a Session

You don’t need to call `session_start()` manually —
Erlenmeyer does this automatically whenever you interact with `Session`.

If a session isn’t active yet, it’s started transparently.

---

## 🧱 Basic API

### Storing and Retrieving Values

```php
Session::set('theme', 'dark');

echo Session::get('theme');        // "dark"
echo Session::get('language', 'en'); // default fallback: "en"
```

### Checking and Removing

```php
if (Session::has('theme')) {
    Session::remove('theme');
}
```

---

## ⚡ Flash Messages

Flash messages are temporary session values that last **for one request only**.

They’re perfect for redirect-based workflows — for example, after a form submission.

```php
// On form submission
Session::flash('success', 'User created successfully!');
return $res->redirect("/login");
```

```php
// On the redirected page
$message = Session::getFlash('success');

if ($message) {
    echo "<p class='alert alert-success'>{$message}</p>";
}
```

The message is automatically removed after being retrieved.

---

## 🧠 How It Works

- Flash messages are stored internally under the `$_SESSION['flash']` array.
- When you call `getFlash()`, the item is returned and immediately removed.
- If the flash container becomes empty, it’s cleared completely.

Example:

```php
Session::flash('notice', 'Settings saved!');
echo Session::getFlash('notice'); // Displays and removes the message
echo Session::getFlash('notice'); // null
```

---

## 🧩 Complete API Reference

| Method                                     | Description                           |
| ------------------------------------------ | ------------------------------------- |
| `Session::set($key, $value)`               | Stores a value in the session         |
| `Session::get($key, $default = null)`      | Retrieves a value or default          |
| `Session::has($key)`                       | Checks if a key exists                |
| `Session::remove($key)`                    | Removes a key from the session        |
| `Session::flash($key, $value)`             | Sets a temporary one-request message  |
| `Session::getFlash($key, $default = null)` | Retrieves and removes a flash message |
| `Session::hasFlash($key)`                  | Checks if a flash message exists      |

---

## 🧩 Example: Login System

```php
$app->post('/login', function ($req, $res) {
    $user = authenticate($req->getFormDataParam('email'), $req->getFormDataParam('password'));

    if (!$user) {
        Session::flash('error', 'Invalid credentials');
        return $res->redirect('/login');
    }

    Session::set('user', $user['id']);
    return $res->redirect('/dashboard');
});

$app->get('/dashboard', function ($req, $res) {
    if (!Session::has('user')) {
        return $res->redirect('/login');
    }

    $res->withHtml('<h1>Welcome to your dashboard</h1>')->send();
});
```

This demonstrates how you can combine sessions and flash messages
to manage authentication in just a few lines.

---

## ⚖️ Notes & Best Practices

✅ **Sessions are automatically started** when needed.
❌ **Do not call `session_start()` manually** — Erlenmeyer handles it.
🧩 **Flash data is temporary** — once read, it’s gone.
🔒 **Always validate user input** before storing it in the session.

---

## 🚀 Summary

| Concept               | Description                                                |
| --------------------- | ---------------------------------------------------------- |
| **Persistent data**   | Store and retrieve session values easily                   |
| **Flash messages**    | Temporary data for redirects and notices                   |
| **Auto-start**        | Sessions start automatically on first use                  |
| **Clean API**         | No direct access to `$_SESSION`                            |
| **Stateless testing** | Works seamlessly with `ErlenClient` for request simulation |

---

!!! tip "Pro tip"
    Combine `Session::flash()` with `Response::redirect()`
    to create elegant, user-friendly feedback loops —
    perfect for form submissions or login flows.
