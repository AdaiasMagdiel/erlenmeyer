# Erlenmeyer Framework

> ⚗️ A lightweight, modular, and elegant PHP microframework for building modern web applications and APIs.

---

## 🧠 What is Erlenmeyer?

Erlenmeyer is a **minimal and composable PHP microframework** that gives you the essential tools — **routing**, **requests**, **responses**, **sessions**, and **testing** — all without imposing any rigid structure.

You decide how your project looks.  
Want a clean MVC layout? Perfect.  
Prefer everything in a single `index.php` file? That works too.

It draws inspiration from the **[Flask](https://flask.palletsprojects.com/)** microframework in Python — sharing the same principles of **simplicity**, **explicitness**, and **developer freedom** — but redesigned from the ground up for the PHP ecosystem.

Erlenmeyer is about **clarity without constraints**.

---

!!! quote "The philosophy" > “Simplicity is the ultimate sophistication.”

> — Leonardo da Vinci

---

## ✨ Key Features

| Category                 | Highlights                                                          |
| ------------------------ | ------------------------------------------------------------------- |
| **Routing**              | Simple syntax with dynamic parameters (`/users/[id]`) and redirects |
| **Middleware**           | Global and per-route, easy `$next($req, $res, $params)` chaining    |
| **Requests & Responses** | Typed API with helpers for HTML, JSON, text, and files              |
| **Logging**              | Console or rotating file-based logs with structured levels          |
| **Error Handling**       | Custom exception mapping and fallback handlers                      |
| **Assets**               | Static file delivery with MIME detection and caching                |
| **Sessions**             | Flash messages and persistent data management                       |
| **Testing**              | `ErlenClient` to simulate full HTTP requests for testing            |

---

## ⚙️ A Quick Example

```php
<?php
use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

require __DIR__ . '/bootstrap.php';

$app = new App();

$app->get('/', fn(Request $req, Response $res) =>
    $res->withHtml('<h1>Hello from Erlenmeyer ⚗️</h1>')
);

$app->get('/hello/[name]', fn(Request $req, Response $res, stdClass $params) =>
    $res->withText("Hello, {$params->name}!")
);

$app->run();
```

Run it with:

```bash
php -S localhost:8000
```

and visit [http://localhost:8000](http://localhost:8000)

---

## 🧩 Typical Project Layout

Here’s a **recommended structure** — not mandatory.
Erlenmeyer doesn’t enforce any directory layout or naming convention.
You’re free to organize your project **your way**.

```
project/
├── app/
│   ├── Controllers/
│   │   └── HomeController.php
│   ├── Models/
│   │   └── User.php
│   ├── Services/
│   │   └── AuthService.php
│   ├── Helpers/
│   │   └── functions.php
│   └── Config/
│       └── database.php
│
├── public/
│   ├── index.php        # Main entry point (routes)
│   └── assets/
│       ├── css/
│       └── js/
│
├── bootstrap.php        # Loads autoload, dotenv, etc.
├── composer.json
├── .env.example
├── .htaccess
└── logs/
    └── info.log
```

!!! tip "Freedom first"
This is just a **suggestion** — not a rule.
You can start small with a single `index.php`,
and only introduce structure as your project grows.

---

## 🔧 The `.htaccess` (recommended)

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

✅ This version improves security and readability.
(Explicit `+FollowSymLinks` and clear file-access rules.)

---

## 🚀 Next Steps

- :rocket: **[Getting Started](getting-started.md)** — install, bootstrap, and run your first route
- :world_map: **[Routing](reference/App.md)** — define endpoints with parameters and middleware
- :gear: **[Core Classes](reference/App.md)** — deep dive into App, Request, Response, and Session APIs

---

## ❤️ Built by a Developer, for Developers

Erlenmeyer was crafted by **[Adaías Magdiel](https://github.com/AdaiasMagdiel)**
to bring back the joy of writing **clean, expressive PHP** — without the weight of a framework.

Because true elegance is having **everything you need, and nothing you don’t.**

---

## ⚖️ License

Erlenmeyer is open-source software licensed under the  
[GNU General Public License v3.0 (GPLv3)](https://www.gnu.org/licenses/gpl-3.0.html).

You are free to use, modify, and distribute it —  
as long as derivative works remain open and share the same freedom.
