# Getting Started

> ⚗️ Build modern PHP apps with simplicity — the Erlenmeyer way.

---

## 🧠 Introduction

Erlenmeyer is designed to make web development **simple, explicit, and unopinionated**.  
There are **no fixed structures**, **no hidden magic**, and **no dependency hell** — just clear, expressive PHP.

You can start small with a single `index.php` file or gradually organize your project as it grows.

If you’ve used [Flask](https://flask.palletsprojects.com/) in Python,  
you’ll feel right at home here.

---

## 📦 Installation

You can install Erlenmeyer through **Composer** — the standard PHP package manager:

```bash
composer require adaiasmagdiel/erlenmeyer
```

That’s it.
No scaffolding, no setup scripts — just import and start coding.

---

## 🧱 Project Setup (Optional)

Erlenmeyer doesn’t enforce any specific layout.
You’re free to organize your app however you want.

A simple yet clean setup might look like this:

```
project/
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   └── Helpers/
├── public/
│   ├── index.php
│   └── assets/
│       ├── css/
│       └── js/
├── bootstrap.php
├── composer.json
├── .htaccess
└── logs/
```

!!! tip "Freedom first"
You can skip all of this and start with a single `index.php`.

    Erlenmeyer will run just fine.

---

## ⚙️ Bootstrap File

Your `bootstrap.php` typically loads the autoloader, environment variables,
and initializes anything your app needs.

```php
<?php
// bootstrap.php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables (optional)
if (file_exists(__DIR__ . '/.env')) {
    Dotenv::createImmutable(__DIR__)->load();
}

// You can also configure logs, sessions, or constants here
```

---

## 🧭 Defining Routes

Let’s create a basic web app in `public/index.php`:

```php
<?php
use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

require __DIR__ . '/../bootstrap.php';

$app = new App();

// Root route
$app->get('/', fn(Request $req, Response $res) =>
    $res->withHtml('<h1>Hello from Erlenmeyer ⚗️</h1>')
);

// Dynamic route with parameter
$app->get('/hello/[name]', fn(Request $req, Response $res, stdClass $params) =>
    $res->withText("Hello, {$params->name}!")
);

$app->run();
```

Visit `http://localhost:8000` and enjoy your first Erlenmeyer app!

---

## 🧩 Adding Middleware

Middlewares let you **run code before or after your routes** —
for authentication, logging, headers, or anything else.

They receive `$req`, `$res`, `$next`, and `$params`.
Here’s a simple example:

```php
<?php
$auth = function ($req, $res, $next, $params) {
    $token = $req->getQueryParam('token');

    if ($token !== 'secret') {
        $res->withText('Unauthorized')
            ->setStatusCode(401)
            ->send();
        return;
    }

    // Continue to the next middleware or route
    $next($req, $res, $params);
};

// Apply middleware globally
$app->addMiddleware($auth);

// Or per route
$app->get('/private', fn($req, $res) =>
    $res->withHtml('<h1>Welcome to the secret area 🧪</h1>'),
    [$auth]
);
```

!!! tip "Flexible design"
Middlewares can modify the request, response, or even stop the flow —

    just like in Flask or Express.js, but with pure PHP.

---

## 🧰 Running the App

You can use PHP’s built-in server to start immediately:

```bash
php -S localhost:8000 -t public
```

Then open [http://localhost:8000](http://localhost:8000) in your browser.

---

## 🧩 Serving Static Files (Optional)

If you use the `Assets` class, Erlenmeyer can automatically handle static files.

Example:

```php
use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Assets;

$assets = new Assets(__DIR__ . '/assets', '/assets');
$app = new App($assets);
```

This setup serves files from `/assets/` (or `/public/assets/`),
with MIME type detection, caching, and 304 responses built-in.

---

## 🔧 Recommended `.htaccess`

If you’re hosting on Apache, use this file to route all requests to `index.php`:

```apache
RewriteEngine On

# General settings
Options -Indexes
Options +FollowSymLinks

# Disable Apache version exposure
Header always unset X-Powered-By

# Allow access to static files
RewriteRule ^(assets|public)/.* - [L]

# Block direct access to PHP files except index.php
RewriteCond %{REQUEST_URI} !/index\.php$ [NC]
RewriteCond %{REQUEST_URI} \.php$ [NC]
RewriteRule ^ - [R=404,L]

# Redirect all other requests to index.php
RewriteRule ^ index.php [L]
```

---

## 🚀 What’s Next

- :map: **[Concepts → Routing](concepts/routing.md)** — how routes, parameters, and middleware work
- :gear: **[Reference → App](reference/App.md)** — deep dive into the `App` class
- :file_folder: **[Assets](concepts/assets.md)** — serving static files and media

---

## ⚖️ License

Erlenmeyer is open-source software licensed under the
[GNU General Public License v3.0 (GPLv3)](https://www.gnu.org/licenses/gpl-3.0.html).

You are free to use, modify, and distribute it —
as long as derivative works remain open and share the same freedom.
