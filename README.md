# Erlenmeyer - Minimal PHP Framework for Web Applications

Erlenmeyer is a lightweight PHP framework designed to create web applications simply and efficiently. The name "Erlenmeyer" is a nod to Flask, a minimal Python framework I admire for its simplicity. However, this project is not intended to mimic Flask exactly or follow its patterns to the letter. Currently in its early stages, Erlenmeyer is ideal for small, lightweight applications without heavy commitments. I built it primarily for my own basic projects to avoid repeatedly setting up the same structure, but it can be useful for anyone seeking a straightforward and practical solution.

## Table of Contents
- [Installation](#installation)
- [Features](#features)
- [Get Started](#get-started)
  - [Configuring .htaccess](#configuring-htaccess)
  - [Minimal Application Example](#minimal-application-example)
  - [Using Middlewares](#using-middlewares)
  - [Configuring CORS](#configuring-cors)
  - [Serving Static Assets](#serving-static-assets)
- [Reference](#reference)
  - [App Class](#app-class)
  - [Request Class](#request-class)
  - [Response Class](#response-class)
- [License](#license)

---

## Installation

To start using Erlenmeyer, install it via Composer with the following command:

```bash
composer require adaiasmagdiel/erlenmeyer
```

Ensure Composer is installed in your environment. After installation, you can include Erlenmeyer in your PHP project using Composer's autoload.

---

## Features

Erlenmeyer provides essential features to build web applications quickly and effectively:

- **Simple Routing**: Define routes for HTTP methods like GET, POST, etc.
- **Middlewares**: Add custom logic before or after requests, globally or per route.
- **CORS Support**: Easily configure Cross-Origin Resource Sharing for APIs.
- **Request and Response Classes**: Handle requests and responses clearly and securely.
- **Static Asset Serving**: Automatically serve files like CSS, JS, and images with caching and security.

> **Note**: The `Assets` class is used internally to manage static files and does not require direct configuration by the user.

---

## Get Started

### Configuring .htaccess

To let Erlenmeyer handle all requests, configure an `.htaccess` file in your project's root to redirect everything to `index.php`. Here's an example:

```apache TODO
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

This ensures all requests are routed through Erlenmeyer, except for existing files or directories.

### Minimal Application Example

Here's a basic example of how to create an application with Erlenmeyer:

```php
<?php
require_once 'vendor/autoload.php';

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

$app = new App();

$app->get('/', function (Request $req, Response $res) {
    $res->withHtml("<h1>Welcome to Erlenmeyer!</h1>")->send();
});

$app->run();
```

In this example:
- A GET route for the root (`/`) is defined.
- The handler function receives `Request` and `Response` classes as parameters.
- The response is a simple HTML page sent to the browser.

### Using Middlewares

Middlewares allow you to add logic before or after request processing. Here's an example of a global middleware that measures execution time:

```php
$app->addMiddleware(function (Request $req, Response $res, callable $next) {
    $start = microtime(true);
    $next($req, $res); // Calls the next handler in the chain
    $time = microtime(true) - $start;
    echo "<p>Execution time: $time seconds</p>";
});
```

For a route-specific middleware, such as authentication:

```php
$app->get('/protected', function (Request $req, Response $res) {
    $res->withHtml("<h1>Protected Route</h1>")->send();
}, [function (Request $req, Response $res, callable $next) {
    if (!isset($_SESSION['user'])) {
        $res->redirect('/login');
    } else {
        $next($req, $res);
    }
}]);
```

### Configuring CORS

Erlenmeyer makes it easy to set up CORS. For a specific route:

```php
$app->get('/api/data', function (Request $req, Response $res) {
    $res->setCORS([
        'origin' => 'https://my-site.com',
        'methods' => ['GET', 'POST'],
        'headers' => ['Content-Type']
    ]);
    $res->withJson(['data' => 'example'])->send();
});
```

To apply CORS globally, use a middleware:

```php
$app->addMiddleware(function (Request $req, Response $res, callable $next) {
    $res->setCORS([
        'origin' => '*',
        'methods' => 'GET,POST',
        'headers' => 'Content-Type'
    ]);
    $next($req, $res);
});
```

### Serving Static Assets

Erlenmeyer automatically serves static files from the `/public` directory (or another configured directory). For example, a file at `/public/css/style.css` is accessible at `/assets/css/style.css`.

To customize the assets directory or route:

```php
$app = new App(__DIR__ . '/my-assets', '/static');
```

This sets `/my-assets` as the base directory and `/static` as the route for accessing files.

---

## Reference

### App Class

The `App` class is the core of Erlenmeyer, managing routes, middlewares, and application execution.

- **Key Methods**:
  - `__construct(string $assetsDir = "/public", string $assetsRoute = "/assets")`: Initializes the application with assets directory and route.
  - `addMiddleware(callable $middleware)`: Adds a global middleware.
  - `set404Handler(callable $action)`: Sets a custom 404 error handler.
  - `route(string $method, string $route, callable $action, array $middlewares = [])`: Registers a generic route.
  - `get(string $route, callable $action, array $middlewares = [])`: Registers a GET route.
  - `post(string $route, callable $action, array $middlewares = [])`: Registers a POST route.
  - `run()`: Starts the application and processes the request.

### Request Class

The `Request` class encapsulates HTTP request data.

- **Key Methods**:
  - `getHeader(string $name): ?string`: Returns the value of a header.
  - `getMethod(): string`: Returns the HTTP method (GET, POST, etc.).
  - `getUri(): string`: Returns the request URI.
  - `getQueryParams(): array`: Returns query string parameters.
  - `getFormData(): array`: Returns form-submitted data.
  - `getJson(bool $assoc = true): mixed`: Returns decoded JSON body.
  - `getRawBody(): ?string`: Returns the raw request body.
  - `getFiles(): array`: Returns uploaded files.
  - `isAjax(): bool`: Checks if the request is AJAX.
  - `isSecure(): bool`: Checks if the connection is secure (HTTPS).

### Response Class

The `Response` class controls the HTTP response sent to the client.

- **Key Methods**:
  - `setStatusCode(int $code): self`: Sets the HTTP status code.
  - `setHeader(string $name, string $value): self`: Sets a header.
  - `setContentType(string $contentType): self`: Sets the content type.
  - `withHtml(string $html): self`: Sets the body as HTML.
  - `withJson($data, int $options = JSON_PRETTY_PRINT): self`: Sets the body as JSON.
  - `withText(string $text): self`: Sets the body as plain text.
  - `redirect(string $url, int $statusCode = 302): self`: Redirects to a URL.
  - `withFile(string $filePath): self`: Sends a file as the response.
  - `setCORS(array $options): self`: Configures CORS headers.
  - `send(): void`: Sends the response to the client.

---

## License

Erlenmeyer is distributed under the **GPLv3** license. See the [LICENSE](LICENSE) file in the repository for more details.
