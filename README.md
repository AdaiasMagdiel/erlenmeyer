# Erlenmeyer - Minimal PHP Framework for Web Applications

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)  
[![Composer](https://img.shields.io/badge/Composer-adaiasmagdiel/erlenmeyer-blue)](https://packagist.org/packages/adaiasmagdiel/erlenmeyer)  
[![GitHub Repository](https://img.shields.io/badge/GitHub-AdaiasMagdiel/Erlenmeyer-blue)](https://github.com/AdaiasMagdiel/Erlenmeyer)

**Erlenmeyer** is a lightweight PHP framework designed for simplicity and efficiency in building web applications. Inspired by the minimalism of Python's Flask, Erlenmeyer is not a direct clone but a unique solution tailored for PHP developers. It is currently in its early stages, making it perfect for small projects, APIs, or microservices where a lean setup is preferred. I created Erlenmeyer to streamline my own projects, but it's open for anyone seeking a straightforward, no-frills framework.

## Table of Contents

- [Introduction](#introduction)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Getting Started](#getting-started)
- [Routing](#routing)
- [Middlewares](#middlewares)
- [Error Handling](#error-handling)
- [Asset Management](#asset-management)
- [Session Management](#session-management)
- [Request and Response Objects](#request-and-response-objects)
- [Logging](#logging)
- [Tests](#tests)
- [Use Cases](#use-cases)
- [License](#license)
- [Reference](#reference)

## Introduction

Erlenmeyer is a lightweight PHP framework designed for simplicity and efficiency in building web applications. Inspired by the minimalism of Python's Flask, Erlenmeyer is not a direct clone but a unique solution tailored for PHP developers. It is currently in its early stages, making it perfect for small projects, APIs, or microservices where a lean setup is preferred. I created Erlenmeyer to streamline my own projects, but it's open for anyone seeking a straightforward, no-frills framework.

**Key Characteristics:**
- **Minimalist**: Small footprint, easy to learn and use.
- **Flexible**: Supports various use cases, from simple scripts to full applications.
- **Extensible**: Built with extensibility in mind, allowing custom functionality.
- **Flask-Inspired**: Familiar routing and handler concepts for developers coming from Python.

## Features

- Simple and intuitive routing system with support for dynamic routes.
- Support for global and route-specific middlewares.
- Custom error handling for 404 errors and exceptions.
- Integrated session management with flash messages.
- Static asset server for CSS, JavaScript, images, and more.
- Comprehensive `Request` and `Response` objects for handling HTTP requests and responses.
- Logging with file rotation for application event monitoring.

## Requirements

- **PHP**: 8.1 or higher
- **Composer**: For dependency management
- **Web Server**: Apache with `mod_rewrite` or Nginx
- **PHP Extensions**: `json`, `mbstring`
- **Optional**: `getallheaders` for enhanced header support (not always necessary)

## Installation

Install Erlenmeyer using Composer:

```bash
composer require adaiasmagdiel/erlenmeyer
```

Include it in your PHP project:

```php
require_once 'vendor/autoload.php';
```

## Getting Started

First, make sure to import the necessary classes:

```php
use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;
```

Create a new instance of the `App` class:

```php
$app = new App();
```

Define routes:

```php
$app->get('/', function (Request $req, Response $res, $params) {
    $res->withHtml('Hello, World!')->send();
});
```

Run the application:

```php
$app->run();
```

## Routing

Erlenmeyer supports various HTTP methods for routing:

```php
$app->get('/path', $handler);
$app->post('/path', $handler);
$app->put('/path', $handler);
$app->delete('/path', $handler);
$app->patch('/path', $handler);
$app->options('/path', $handler);
$app->head('/path', $handler);
$app->any('/path', $handler); // Matches any HTTP method
$app->match(['GET', 'POST'], '/path', $handler); // Matches specified methods
```

### Dynamic Routes

Define routes with parameters:

```php
$app->get('/user/[id]', function (Request $req, Response $res, $params) {
    $id = $params->id;
    $res->withHtml("User ID: $id")->send();
});
```

### Redirects

Set up redirects:

```php
$app->redirect('/old', '/new', false); // Temporary redirect (302)
```

## Middlewares

Add global middlewares:

```php
$app->addMiddleware(function (Request $req, Response $res, callable $next, $params) {
    // Middleware logic
    $next($req, $res, $params);
});
```

Add route-specific middlewares:

```php
$app->get('/admin', $handler, [$middleware1, $middleware2]);
```

## Error Handling

Set a custom 404 handler:

```php
$app->set404Handler(function (Request $req, Response $res, $params) {
    $res->setStatusCode(404)->withHtml('Custom 404')->send();
});
```

Set exception handlers:

```php
$app->setExceptionHandler(\Exception::class, function (Request $req, Response $res, \Exception $e) {
    $res->setStatusCode(500)->withHtml('Error: ' . $e->getMessage())->send();
});
```

## Asset Management

Serve static assets by creating an instance of `Assets`:

```php
use AdaiasMagdiel\Erlenmeyer\Assets;

$assets = new Assets(assetsDirectory: __DIR__ . '/public', assetsRoute: '/assets');
$app = new App(assets: $assets);
```

Access assets via `/assets/file.ext`. For example:

```html
<link rel="stylesheet" href="/assets/css/style.css">
```

## Session Management

Use the `Session` class to manage sessions:

```php
use AdaiasMagdiel\Erlenmeyer\Session;

Session::set('key', 'value');
$value = Session::get('key', 'default');
Session::flash('message', 'Flash message');
$flash = Session::getFlash('message');
```

## Request and Response Objects

### Request

The `Request` object provides access to request data:

```php
$method = $req->getMethod();
$uri = $req->getUri();
$queryParams = $req->getQueryParams();
$formData = $req->getFormData();
$jsonData = $req->getJson();
$files = $req->getFiles();
$isAjax = $req->isAjax();
$isSecure = $req->isSecure();
```

### Response

The `Response` object allows building responses:

```php
$res->withHtml('HTML content');
$res->withJson(['key' => 'value']);
$res->withText('Plain text');
$res->withFile('/path/to/file');
$res->redirect('/path');
$res->setStatusCode(404);
$res->setHeader('Key', 'Value');
$res->setCORS(['origin' => '*', 'methods' => 'GET,POST']);
$res->send();
```

## Logging

Erlenmeyer provides a flexible and extensible logging system to help you monitor and debug your application. Logging is essential for tracking events, identifying issues, and understanding application behavior. The logging system is based on the `LoggerInterface`, which defines the contract for logging messages and exceptions.

### Using Loggers

Erlenmeyer provides two built-in loggers: `FileLogger` for file-based logging with rotation, and `ConsoleLogger` for logging to the command line using `error_log`. You can choose either by passing an instance to the `App` constructor.

**Example with FileLogger:**

```php
use AdaiasMagdiel\Erlenmeyer\Logging\FileLogger;
use AdaiasMagdiel\Erlenmeyer\App;

$logger = new FileLogger('/path/to/logs');
$app = new App(logger: $logger);
```

- Logs are written to `/path/to/logs/info.log`.
- The `FileLogger` automatically rotates the log file when it exceeds 3MB, keeping up to 5 rotated files (e.g., `info.log.1`, `info.log.2`, etc.).
- If no log directory is provided, `FileLogger` will not log anything.

**Example with ConsoleLogger:**

```php
use AdaiasMagdiel\Erlenmeyer\Logging\ConsoleLogger;
use AdaiasMagdiel\Erlenmeyer\App;

$logger = new ConsoleLogger();
$app = new App(logger: $logger);
```

- Logs are output to the command line using `error_log`, appearing in the terminal or server error log.
- No configuration is required for `ConsoleLogger`, making it ideal for debugging or CLI environments.

### Log Levels

The logging system supports the following levels, defined in the `LogLevel` enum:

| Level      | Description                                   |
|------------|-----------------------------------------------|
| `INFO`     | General operational information               |
| `DEBUG`    | Detailed debug information                    |
| `WARNING`  | Indicates something unexpected but recoverable |
| `ERROR`    | Indicates a serious error affecting functionality |
| `CRITICAL` | Indicates a critical error that may cause the application to crash |

These levels can be used when logging messages to categorize their severity.

### Creating a Custom Logger

If you need advanced logging features, such as logging to a database, sending logs to an external service, or using a custom format, you can create a custom logger by implementing the `LoggerInterface`.

**Example of a Custom Logger:**

```php
use AdaiasMagdiel\Erlenmeyer\Logging\LoggerInterface;
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;
use Exception;
use AdaiasMagdiel\Erlenmeyer\Request;

class CustomLogger implements LoggerInterface
{
    private $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    public function log(LogLevel $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level->value] $message\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }

    public function logException(Exception $e, ?Request $request = null): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = "[$timestamp] [ERROR] Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        if ($request) {
            $message .= "Request: " . $request->getMethod() . " " . $request->getUri() . "\n";
        }
        $message .= $e->getTraceAsString() . "\n";
        file_put_contents($this->logFile, $message, FILE_APPEND);
    }
}
```

To use the custom logger, pass an instance to the `App` constructor:

```php
$customLogger = new CustomLogger('/path/to/custom.log');
$app = new App(logger: $customLogger);
```

**Important Note:** If no logger is explicitly provided, the `App` will create a `FileLogger` without a log directory, which means no logging will occur. You must configure a logger explicitly to enable logging.

## Tests

Erlenmeyer uses PestPHP for testing. Run tests with:

```bash
./composer/bin/pest
```

## Use Cases

Erlenmeyer is suitable for a wide range of web applications. Here are some example use cases:

| Use Case | Description |
|----------|-------------|
| **Simple REST API** | Create an API with endpoints for GET, POST, PUT, and DELETE, returning JSON responses. |
| **Basic Web Application** | Develop an application with routes for HTML pages and session management. |
| **Static Page Generator** | Serve static assets like CSS and JavaScript for landing pages. |
| **Forms and Uploads** | Handle POST forms and file uploads with validation. |

**Example REST API:**

```php
$app->get('/api/users/[id]', function (Request $req, Response $res, $params) {
    $id = $params->id;
    $res->withJson(['id' => $id, 'name' => 'User ' . $id])->send();
});
```

**Example Form Handling:**

```php
$app->post('/submit', function (Request $req, Response $res, $params) {
    $name = $req->getFormDataParam('name', 'Guest');
    Session::flash('message', 'Form submitted successfully!');
    $res->redirect('/thank-you')->send();
});
```

## License

Erlenmeyer is licensed under the GPLv3. See the [LICENSE](LICENSE) and the [COPYRIGHT](COPYRIGHT) files for details.

## Reference

### App

| Method | Description |
|--------|-------------|
| `__construct(?Assets $assets = null, ?string $logDir = null)` | Initializes the application. |
| `route(string $method, string $route, callable $action, array $middlewares = [])` | Registers a route for an HTTP method. |
| `get(string $route, callable $action, array $middlewares = [])` | Registers a GET route. |
| `post(string $route, callable $action, array $middlewares = [])` | Registers a POST route. |
| `any(string $route, callable $action, array $middlewares = [])` | Registers a route for any HTTP method. |
| `match(array $methods, string $route, callable $action, array $middlewares = [])` | Registers a route for specified methods. |
| `redirect(string $from, string $to, bool $permanent = false)` | Registers a redirect. |
| `set404Handler(callable $action)` | Sets the 404 error handler. |
| `addMiddleware(callable $middleware)` | Adds a global middleware. |
| `setExceptionHandler(string $exceptionClass, callable $handler)` | Sets an exception handler. |
| `run()` | Runs the application. |

### Assets

| Method | Description |
|--------|-------------|
| `__construct(string $assetsDirectory = "/public", string $assetsRoute = "/assets")` | Initializes the asset manager. |
| `getAssetsDirectory(): string` | Returns the assets directory. |
| `getAssetsRoute(): string` | Returns the assets route. |
| `isAssetRequest(): bool` | Checks if the request is for an asset. |
| `serveAsset(): bool` | Serves the requested asset. |

### Session

| Method | Description |
|--------|-------------|
| `static get(string $key, $default = null)` | Gets a session value. |
| `static set(string $key, $value)` | Sets a session value. |
| `static has(string $key): bool` | Checks if a session key exists. |
| `static remove(string $key)` | Removes a session key. |
| `static flash(string $key, $value)` | Sets a flash message. |
| `static getFlash(string $key, $default = null)` | Gets and removes a flash message. |
| `static hasFlash(string $key): bool` | Checks if a flash message exists. |

### Request

| Method | Description |
|--------|-------------|
| `__construct(?array $server = null, ?array $get = null, ?array $post = null, ?array $files = null, string $inputStream = 'php://input')` | Initializes the request. |
| `getHeader(string $name): ?string` | Gets a specific header. |
| `getHeaders(): array` | Gets all headers. |
| `getMethod(): string` | Gets the HTTP method. |
| `getUri(): string` | Gets the request URI. |
| `getQueryParams(): array` | Gets query parameters. |
| `getFormData(): array` | Gets form data. |
| `getJson(): mixed` | Gets JSON data from the body. |
| `getFiles(): array` | Gets uploaded files. |
| `isAjax(): bool` | Checks if the request is AJAX. |
| `isSecure(): bool` | Checks if the request is secure (HTTPS). |

### Response

| Method | Description |
|--------|-------------|
| `__construct(int $statusCode = 200, array $headers = [])` | Initializes the response. |
| `setStatusCode(int $code): self` | Sets the HTTP status code. |
| `getStatusCode(): int` | Gets the HTTP status code. |
| `setHeader(string $name, string $value): self` | Sets an HTTP header. |
| `removeHeader(string $name): self` | Removes an HTTP header. |
| `getHeaders(): array` | Gets all headers. |
| `setContentType(string $contentType): self` | Sets the content type. |
| `getContentType(): string` | Gets the content type. |
| `setBody(string $body): self` | Sets the response body. |
| `getBody(): ?string` | Gets the response body. |
| `withHtml(string $html): self` | Sets HTML content. |
| `withTemplate(string $templatePath, array $data = []): self` | Sets content from a template. |
| `withJson($data, int $options = JSON_PRETTY_PRINT): self` | Sets JSON content. |
| `withText(string $text): self` | Sets plain text content. |
| `redirect(string $url, int $statusCode = 302): self` | Sets a redirect. |
| `withCookie(string $name, string $value, int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = true): self` | Sets a cookie. |
| `send(): void` | Sends the response. |
| `isSent(): bool` | Checks if the response has been sent. |
| `clear(): self` | Clears the response body and headers. |
| `withError(int $statusCode, string $message = '', ?callable $logger = null): self` | Sets an error response. |
| `withFile(string $filePath): self` | Sets the response to send a file. |
| `setCORS(array $options): self` | Configures CORS headers. |
