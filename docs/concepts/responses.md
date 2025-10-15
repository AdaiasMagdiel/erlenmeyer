# Handling Responses

The `Response` object in Erlenmeyer gives you full control over what is sent back to the client —  
whether it’s plain text, HTML, JSON, or file downloads.

It’s designed to be **explicit and immutable**: each method returns the same `Response` instance,  
allowing for fluent chaining and predictable output.

---

## Sending Basic Responses

The simplest way to send a response:

```php
$app->get('/hello', function ($req, $res) {
    $res->withText('Hello, world!')->send();
});
```

This sets a `Content-Type: text/plain; charset=UTF-8` header
and flushes the text directly to the client.

---

## Sending HTML

```php
$app->get('/', function ($req, $res) {
    $html = "<h1>Welcome to Erlenmeyer ⚗️</h1>";
    $res->withHtml($html)->send();
});
```

By default, the framework adds:

```
Content-Type: text/html; charset=UTF-8
```

---

## Sending JSON

```php
$app->get('/api/info', function ($req, $res) {
    $data = ['status' => 'ok', 'framework' => 'Erlenmeyer'];
    $res->withJson($data)->send();
});
```

Internally, `withJson()`:

- Encodes arrays and objects via `json_encode()`,
- Sets the `Content-Type: application/json` header,
- And automatically applies UTF-8 encoding.

---

## Setting Status Codes

You can easily set status codes before sending:

```php
$res->withText('Unauthorized')->setStatusCode(401)->send();
```

or chain fluently:

```php
$res->setStatusCode(201)->withJson(['created' => true])->send();
```

---

## Redirects

Redirects are just another response type:

```php
$res->redirect('/new-location');
```

You can specify whether it’s permanent:

```php
$res->redirect('/new-home', 301);
```

---

## Custom Headers

Add or replace headers using `setHeader()`:

```php
$res->setHeader('X-Powered-By', 'Erlenmeyer')
    ->withText('OK')
    ->send();
```

Or multiple at once:

```php
$res->setHeaders([
    'Cache-Control' => 'no-store',
    'X-Frame-Options' => 'DENY',
])->withText('Secure')->send();
```

---

## Sending Files

For binary or downloadable responses:

```php
$app->get('/download', function ($req, $res) {
    $res->withFile('assets/banner.jpg')->send();
});
```

This automatically sets the correct MIME type and streams the file content.

---

## JSON Error Helpers

For quick API responses:

```php
$res->withError(404, 'Not found')->send();
```

This produces a JSON body like:

```json
{
  "error": "Not found",
  "status": 404
}
```

and sets both `Content-Type: application/json` and HTTP 404.

---

## Working with Cookies

You can add cookies easily:

```php
$res->setCookie('session', 'abc123', [
    'path' => '/',
    'httponly' => true,
    'secure' => true,
]);
```

And clear them:

```php
$res->clearCookie('session');
```

---

## Combining Responses and Middleware

Because the response is mutable, it can be safely modified in middlewares:

```php
$headers = function ($req, $res, $next, $params) {
    $res->setHeader('X-Request-ID', uniqid());
    $next($req, $res, $params);
};
```

!!! tip "Functional design"
	Middlewares and handlers share the same `Response` instance,
	so all mutations (headers, status codes, cookies) persist across the chain.

---

## Full Example

```php
$app->get('/profile', function ($req, $res) {
    $user = ['id' => 1, 'name' => 'Ada Lovelace'];

    $res->setHeader('X-Powered-By', 'Erlenmeyer')
        ->setStatusCode(200)
        ->withJson($user)
        ->send();
});
```

Response headers:

```
HTTP/1.1 200 OK
Content-Type: application/json; charset=UTF-8
X-Powered-By: Erlenmeyer
```

Body:

```json
{ "id": 1, "name": "Ada Lovelace" }
```

---

## Summary

| Method                          | Description           |
| ------------------------------- | --------------------- |
| `withText($text)`               | Sends plain text      |
| `withHtml($html)`               | Sends HTML content    |
| `withJson($data)`               | Sends JSON payload    |
| `withFile($path)`               | Streams a file        |
| `setHeader($key, $value)`       | Sets a single header  |
| `setHeaders(array $headers)`    | Sets multiple headers |
| `setStatusCode($code)`          | Sets HTTP status      |
| `redirect($url, $code = 302)`   | Redirects the request |
| `withError($code, $message)`    | Sends a JSON error    |
| `setCookie()` / `clearCookie()` | Manage cookies        |

---

!!! note "Under the hood"
	Erlenmeyer buffers all response data until you call `send()`.
	This gives you freedom to build, modify, and compose responses
	before anything is sent to the client — full control, zero magic.
