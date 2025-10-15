# Serving Static Assets

Erlenmeyer includes a lightweight, built-in system for serving **static files** —  
such as images, CSS, JavaScript, or other public resources — through the `Assets` class.

It’s fully optional, but extremely convenient for small or self-contained projects.

---

## 🧩 Overview

When you provide an `Assets` instance to your `App`, Erlenmeyer automatically:

- Detects static requests (e.g., `/assets/style.css`)
- Resolves the correct file path safely
- Sends the file with proper **MIME type**, **cache headers**, and **ETag support**
- Returns `304 Not Modified` when the client already has a cached copy

If the requested file doesn’t exist, the 404 handler is triggered.

---

## ⚙️ Basic Setup

To enable asset serving, create an `Assets` instance and pass it to the `App` constructor:

```php
use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Assets;

$assets = new Assets(__DIR__ . '/assets', '/assets');
$app = new App($assets);
```

Now any request to `/assets/...` will automatically serve files
from the `/assets` folder in your project.

Example:

```
/assets/style.css  →  ./assets/style.css
/assets/logo.png   →  ./assets/logo.png
```

---

## 📁 Typical Directory Structure

```
project/
├── public/
│   ├── index.php
│   └── assets/
│       ├── css/
│       │   └── style.css
│       ├── js/
│       │   └── app.js
│       └── images/
│           └── logo.png
```

In this setup, your entry point (`index.php`) might look like this:

```php
$assets = new Assets(__DIR__ . '/assets', '/assets');
$app = new App($assets);
```

And then run with:

```bash
php -S localhost:8000 -t public
```

Now visiting `http://localhost:8000/assets/css/style.css`
will deliver the file automatically.

---

## 🧠 How It Works

When the `Assets` manager is active:

1. Erlenmeyer checks if the current request matches the assets route prefix (e.g., `/assets`).
2. It resolves the requested path **safely** inside the configured directory.
3. It sets standard headers for performance and caching:

   - `Content-Type`
   - `Content-Length`
   - `Cache-Control`
   - `ETag`
   - `Last-Modified`

4. If the client already has the file cached (`If-None-Match` or `If-Modified-Since`),
   a **304 Not Modified** response is sent instead of retransmitting the file.

This happens **before** the 404 handler or any route logic — so you don’t need to define routes for assets manually.

---

## 🔒 Security

The `Assets` class uses **strict path validation** to prevent directory traversal.
It ensures all served files reside inside the configured directory.

If a user tries to request something outside (like `../../config.php`),
the request is rejected with a 404.

---

## 🧩 Custom Asset Route

You can change the public route prefix.
For example, to serve files under `/static/` instead of `/assets/`:

```php
$assets = new Assets(__DIR__ . '/assets', '/static');
$app = new App($assets);
```

Now `/static/logo.png` will map to `./assets/logo.png`.

---

## 🧰 MIME Detection

Erlenmeyer automatically determines the **MIME type** of the file based on its extension.

| File Type                             | MIME Type                |
| ------------------------------------- | ------------------------ |
| `.css`                                | `text/css`               |
| `.js`                                 | `application/javascript` |
| `.png`, `.jpg`, `.svg`                | Appropriate image MIME   |
| `.json`, `.xml`, `.pdf`, `.zip`, etc. | Fully supported          |

All common web file types are included — you rarely need to configure anything manually.

---

## ⚙️ Conditional Requests & Caching

When possible, Erlenmeyer responds with:

- `ETag` and `Last-Modified` headers for cache validation
- `Cache-Control: public, max-age=86400` (1 day)
- `304 Not Modified` when the resource hasn’t changed

This makes your app efficient even without a CDN.

---

## 🧱 Example: Manual Asset Check

You can check or serve an asset manually if needed:

```php
use AdaiasMagdiel\Erlenmeyer\Request;

$req = new Request();
if ($assets->isAssetRequest($req)) {
    $assets->serveAsset($req);
}
```

Normally, you don’t need to call this —
the framework does it automatically when no route matches.

---

## 🧩 Error Handling

If an asset cannot be found or accessed:

- The HTTP status code is set to **404**
- The message `File not found` is sent
- The logger records the failed attempt (if logging is enabled)

---

## 🚀 Summary

| Concept                 | Description                                        |
| ----------------------- | -------------------------------------------------- |
| **Assets class**        | Handles static file serving                        |
| **Automatic detection** | Requests under the assets route are auto-served    |
| **Safe paths**          | Prevents directory traversal attacks               |
| **MIME types**          | Automatically determined per file                  |
| **Caching**             | Supports ETag, Last-Modified, and 304 responses    |
| **Configurable route**  | `/assets` by default, customizable via constructor |

---

!!! tip "Use it only when you need it"
    In production setups (especially behind Nginx or Apache),
    it’s best to let the web server handle static files directly.
    The built-in `Assets` class is ideal for local development, testing,
    or self-contained PHP deployments.
