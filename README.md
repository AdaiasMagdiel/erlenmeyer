# Erlenmeyer - Minimal PHP Framework for Web Applications

Erlenmeyer is a lightweight PHP framework designed for simplicity and efficiency in building web applications. Inspired by the minimalism of Python's Flask, Erlenmeyer is not a direct clone but a unique solution tailored for PHP developers. Currently in its early stages, it's perfect for small projects, APIs, or microservices where a lean setup is preferred. I created Erlenmeyer to streamline my own projects, but it's open for anyone seeking a straightforward, no-frills framework.

## Why Erlenmeyer?

- **Lightweight**: Minimal dependencies and a small footprint.
- **Flexible**: Easy to customize for small or medium-sized applications.
- **Practical**: Simplifies routing, middleware, and static asset handling.
- **Developer-Friendly**: Clear API with intuitive `Request` and `Response` classes.

## Table of Contents

- Requirements
- Installation
- Features
- Get Started
  - Configuring Web Server
  - Minimal Application Example
  - Using Middlewares
  - Configuring CORS
  - Serving Static Assets
  - Dynamic Routes
- Advanced Usage
  - Handling File Uploads
  - Using Templates
- Testing
- Reference
  - App Class
  - Assets Class
  - Request Class
  - Response Class
- Error Handling
- Contributing
- License

---

## Requirements

To use Erlenmeyer, ensure your environment meets the following:

- PHP 8.1 or higher
- Composer
- Web server: Apache with `mod_rewrite` or Nginx
- PHP extensions: `json`, `mbstring`
- Optional: `getallheaders` for enhanced header support

---

## Installation

Install Erlenmeyer via Composer:

```bash
composer require adaiasmagdiel/erlenmeyer
```

Ensure Composer is installed. After installation, include Erlenmeyer in your PHP project using Composer's autoload:

```php
require_once 'vendor/autoload.php';
```

---

## Features

Erlenmeyer provides essential tools for rapid web development:

- **Simple Routing**: Define routes for HTTP methods (GET, POST, etc.), including dynamic routes with parameters.
- **Middlewares**: Apply custom logic globally or per route.
- **CORS Support**: Configure Cross-Origin Resource Sharing for APIs.
- **Request and Response Classes**: Handle HTTP requests and responses securely.
- **Static Asset Serving**: Serve CSS, JS, and images with caching and security.

  > **Tip**: Disable auto-serving for full control over static files.

---

## Get Started

### Configuring Web Server

Erlenmeyer requires URL rewriting to route requests through `index.php`. Below are configurations for common web servers.

#### Apache (.htaccess)

Place the following `.htaccess` file in your project root:

```apache
RewriteEngine On
RewriteBase /

# Serve existing files/directories directly
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

#### Nginx

Add the following to your Nginx server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

> **Note**: Ensure your web server has write permissions for the assets directory if auto-serving is enabled.

### Minimal Application Example

Create a simple application:

```php
<?php
require_once 'vendor/autoload.php';

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

// With auto-serving enabled (default)
$app = new App();

// With auto-serving disabled
// $app = new App(autoServeAssets: false);

$app->get('/', function (Request $req, Response $res, $params) {
    $res->withHtml("<h1>Welcome to Erlenmeyer!</h1>")->send();
});

$app->run();
```

This example:

- Defines a GET route for the root (`/`).
- Sends a simple HTML response using the `Response` class.

### Using Middlewares

Middlewares add logic before or after request processing. Example of a global middleware to measure execution time:

```php
$app->addMiddleware(function (Request $req, Response $res, callable $next, $params) {
    $start = microtime(true);
    $next($req, $res, $params);
    $time = microtime(true) - $start;
    echo "<p>Execution time: $time seconds</p>";
});
```

For a route-specific middleware (e.g., authentication):

```php
$app->get('/protected/[id]', function (Request $req, Response $res, $params) {
    $res->withHtml("<h1>Protected Route for ID: {$params->id}</h1>")->send();
}, [function (Request $req, Response $res, callable $next, $params) {
    if (!isset($_SESSION['user'])) {
        $res->redirect('/login');
    } else {
        $next($req, $res, $params);
    }
}]);
```

> **Note**: Middlewares for dynamic routes receive a `$params` object containing route parameters (e.g., `$params->id`).

### Configuring CORS

Set CORS for a specific route:

```php
$app->get('/api/data', function (Request $req, Response $res, $params) {
    $res->setCORS([
        'origin' => 'https://my-site.com',
        'methods' => ['GET', 'POST'],
        'headers' => ['Content-Type']
    ]);
    $res->withJson(['data' => 'example'])->send();
});
```

Apply CORS globally with a middleware:

```php
$app->addMiddleware(function (Request $req, Response $res, callable $next, $params) {
    $res->setCORS([
        'origin' => ['https://my-site.com', 'https://api.my-site.com'],
        'methods' => 'GET,POST',
        'headers' => 'Content-Type',
        'credentials' => true
    ]);
    $next($req, $res, $params);
});
```

> **Tip**: Avoid `origin: '*'` in production. Specify trusted domains for security.

### Serving Static Assets

Erlenmeyer serves static files (e.g., CSS, JS, images) from the `/public` directory by default, accessible at `/assets/*`. The `Assets` class ensures security with path traversal prevention and performance with caching headers (`ETag`, `Last-Modified`).

Customize the assets directory or route:

```php
$app = new App(__DIR__ . '/my-assets', '/static');
```

To disable auto-serving:

```php
$app = new App(autoServeAssets: false);
```

### Dynamic Routes

Erlenmeyer supports dynamic routes with parameters, such as `/user/[id]` or `/blog/[category]/[slug]`. Parameters are extracted from the URL and passed to the route handler as a single object (`$params`), where each parameter is accessible as a property (e.g., `$params->id`).

Example:

```php
$app->get('/user/[id]', function (Request $req, Response $res, $params) {
    if (!isset($params->id)) {
        $res->withError(400, 'Missing user ID')->send();
        return;
    }
    $res->withJson(['user_id' => $params->id])->send();
});

$app->get('/blog/[category]/[slug]', function (Request $req, Response $res, $params) {
    if (!isset($params->category) || !isset($params->slug)) {
        $res->withError(400, 'Missing parameters')->send();
        return;
    }
    $res->withJson([
        'category' => $params->category,
        'slug' => $params->slug
    ])->send();
});
```

- **How it works**:
  - For the URL `/user/123`, the handler receives `$params->id = '123'`.
  - For `/blog/tech/my-post`, the handler receives `$params->category = 'tech'` and `$params->slug = 'my-post'`.
  - Parameters are automatically extracted from the route pattern and passed as an object.

> **Tip**: Always validate `$params` properties to handle missing or invalid values.

---

## Advanced Usage

### Using Templates

Render HTML from a template file:

```php
$app->get('/template', function (Request $req, Response $res, $params) {
    $res->withTemplate(__DIR__ . '/views/template.php', ['name' => 'User'])->send();
});
```

Example template (`views/template.php`):

```php
<!DOCTYPE html>
<html>
<head>
    <title>Erlenmeyer Template</title>
</head>
<body>
    <h1>Hello, <?php echo htmlspecialchars($name); ?>!</h1>
</body>
</html>
```

---

## Reference

### App Class

The `App` class is the core of Erlenmeyer, managing routes, middlewares, and execution.

- **Key Methods**:
  - `__construct(string $assetsDir = "/public", string $assetsRoute = "/assets", bool $autoServeAssets = true)`: Initializes the application.
  - `addMiddleware(callable $middleware)`: Adds a global middleware. The middleware receives `Request`, `Response`, `callable $next`, and an optional `$params` object for dynamic routes.
  - `set404Handler(callable $action)`: Sets a custom 404 handler.
  - `route(string $method, string $route, callable $action, array $middlewares = [])`: Registers a generic route. The `$action` receives `Request`, `Response`, and a `$params` object (stdClass) for dynamic routes (e.g., `/user/[id]`).
  - `get(string $route, callable $action, array $middlewares = [])`: Registers a GET route. The `$action` receives `Request`, `Response`, and a `$params` object for dynamic routes.
  - `post(string $route, callable $action, array $middlewares = [])`: Registers a POST route. The `$action` receives `Request`, `Response`, and a `$params` object for dynamic routes.
  - `run()`: Starts the application.

### Assets Class

The `Assets` class manages static file serving with security and caching.

- **Key Methods**:
  - `isAssetRequest(): bool`: Checks if the request is for a static asset.
  - `serveAsset(): bool`: Serves the asset with headers (e.g., `Content-Type`, `ETag`).
  - `detectMimeType(string $filePath): string`: Detects the MIME type based on file extension.

### Request Class

The `Request` class encapsulates HTTP request data and supports dependency injection for testing.

- **Key Methods**:
  - `getHeader(string $name): ?string`: Returns a header value.
  - `getMethod(): string`: Returns the HTTP method.
  - `getUri(): string`: Returns the request URI.
  - `getQueryParams(): array`: Returns query parameters.
  - `getFormData(): array`: Returns POST form data.
  - `getJson(bool $assoc = true): mixed`: Returns decoded JSON body. Throws `RuntimeException` if decoding fails.
  - `getRawBody(): ?string`: Returns the raw request body.
  - `getFiles(): array`: Returns uploaded files.
  - `isAjax(): bool`: Checks if the request is AJAX.
  - `isSecure(): bool`: Checks if the connection is HTTPS.

### Response Class

The `Response` class manages HTTP responses.

- **Key Methods**:
  - `setStatusCode(int $code): self`: Sets the HTTP status code.
  - `setHeader(string $name, string $value): self`: Sets a header.
  - `setContentType(string $contentType): self`: Sets the content type.
  - `withHtml(string $html): self`: Sets HTML content.
  - `withJson($data, int $options = JSON_PRETTY_PRINT): self`: Sets JSON content. Throws `RuntimeException` if serialization fails.
  - `withText(string $text): self`: Sets plain text content.
  - `redirect(string $url, int $statusCode = 302): self`: Redirects to a URL.
  - `withFile(string $filePath): self`: Sends a file as a download. Uses `Assets::detectMimeType` for MIME type.
  - `setCORS(array $options): self`: Configures CORS headers.
  - `send(): void`: Sends the response.

---

## Error Handling

Erlenmeyer throws exceptions in specific cases:

- `InvalidArgumentException`: Invalid parameters (e.g., invalid HTTP method, assets directory, or status code).
- `RuntimeException`: Runtime issues (e.g., response already sent, JSON decoding/serialization errors, or unreadable files).

Use try-catch blocks to handle errors gracefully:

```php
$app->get('/data', function (Request $req, Response $res, $params) {
    try {
        $data = $req->getJson();
        $res->withJson(['result' => $data])->send();
    } catch (RuntimeException $e) {
        $res->withError(400, $e->getMessage())->send();
    }
});
```

---

## Contributing

Erlenmeyer is in its early stages, and contributions are welcome! To report bugs, suggest features, or submit pull requests, visit the [GitHub repository](https://github.com/adaiasmagdiel/erlenmeyer).

---

## License

Erlenmeyer is licensed under the **GPLv3**. See the [LICENSE](LICENSE) file for details.
