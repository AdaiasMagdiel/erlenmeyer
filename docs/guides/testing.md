# Testing Your Application

Erlenmeyer includes a built-in testing utility called **ErlenClient**,  
designed to simulate real HTTP requests **without a web server**.

It lets you fully test routes, middlewares, authentication, errors,  
and even CORS — directly inside PHP, fast and isolated.

---

## 🧩 Why ErlenClient?

ErlenClient acts like a **mini HTTP client** that runs inside the same process  
as your app. This means:

- No need to start `php -S` or a local server.
- Requests pass through **the same middleware, routes, and handlers**.
- You can test APIs, sessions, and even global middleware logic easily.

---

## ⚙️ Basic Setup

Let’s start with a simple test structure:

```

project/
├── app/
│   └── App.php
├── tests/
│   ├── bootstrap.php
│   └── ExampleTest.php
└── public/
└── index.php

```

### Example: `tests/bootstrap.php`

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use AdaiasMagdiel\Erlenmeyer\App;

$app = new App();

$app->get('/hello', fn($req, $res, $params) =>
    $res->withJson(['message' => 'Hello, Tests!'])
);

return $app;
```

---

## 🧪 Writing Your First Test

```php
<?php
use AdaiasMagdiel\Erlenmeyer\Testing\ErlenClient;

$app = require __DIR__ . '/bootstrap.php';
$client = new ErlenClient($app);

$response = $client->get('/hello');

echo $response->getStatusCode(); // 200
echo $response->getBody();       // {"message":"Hello, Tests!"}
```

✅ This test runs entirely in memory — no HTTP calls, no external dependencies.

---

## 🧱 Testing JSON Endpoints

To simulate a `POST` request with a JSON body:

```php
$response = $client->post('/users', [
    'json' => ['name' => 'Alice']
]);

echo $response->getStatusCode(); // 201
echo $response->getBody();       // {"message":"User created","user":{"name":"Alice"}}
```

### Assertions (for PHPUnit or Pest)

```php
$this->assertEquals(201, $response->getStatusCode());
$this->assertStringContainsString('Alice', $response->getBody());
```

---

## 🧩 Sending Headers and Tokens

You can add headers globally or per request.

### Global Headers

```php
$client->withHeaders(['Authorization' => 'Bearer test123']);
```

### Per Request

```php
$response = $client->get('/profile', [
    'headers' => ['Authorization' => 'Bearer test123']
]);
```

All headers are automatically transformed into the proper PHP `$_SERVER` keys
(`HTTP_AUTHORIZATION`, `CONTENT_TYPE`, etc.) before dispatch.

---

## 🧠 Testing Middleware and Auth

Global and route-specific middleware logic is fully executed in tests.

### Example

```php
$app->addMiddleware(function ($req, $res, $next, $params) {
    $token = $req->getHeader('Authorization');
    if ($token !== 'Bearer valid123') {
        $res->withJson(['error' => 'Unauthorized'])->setStatusCode(401)->send();
        return;
    }
    $params->user = ['id' => 1, 'name' => 'Alice'];
    $next($req, $res, $params);
});

$app->get('/me', fn($req, $res, $params) =>
    $res->withJson($params->user)
);
```

Test:

```php
$response = $client->get('/me', [
    'headers' => ['Authorization' => 'Bearer valid123']
]);

$this->assertEquals(200, $response->getStatusCode());
$this->assertStringContainsString('Alice', $response->getBody());
```

---

## 🧩 Testing CORS and Preflight Requests

CORS is easy to test — just send an `OPTIONS` request.

```php
$response = $client->options('/users');

$this->assertEquals(204, $response->getStatusCode());
$this->assertEquals('*', $response->getHeaders()['Access-Control-Allow-Origin']);
```

If your global middleware sets CORS headers, they will appear in the response automatically.

---

## ⚙️ Testing Query Strings and Form Data

### Query Strings

```php
$response = $client->get('/search', [
    'query' => ['q' => 'test']
]);

$this->assertStringContainsString('test', $response->getBody());
```

### Form Data

```php
$response = $client->post('/submit', [
    'form_params' => ['email' => 'user@example.com']
]);

$this->assertEquals(200, $response->getStatusCode());
```

---

## 🧱 Testing File Uploads

ErlenClient supports simulated file uploads as well:

```php
$response = $client->post('/upload', [
    'files' => [
        'avatar' => [
            'name' => 'photo.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => __DIR__ . '/fixtures/photo.jpg',
            'error' => 0,
            'size' => 2048
        ]
    ]
]);
```

---

## 🧩 Testing Error Handling

You can verify custom exception and 404 handlers easily.

### 404 Example

```php
$response = $client->get('/does-not-exist');
$this->assertEquals(404, $response->getStatusCode());
```

### Exception Example

If a route throws an error:

```php
$app->get('/boom', fn() => throw new Exception('Boom!'));
```

Test:

```php
$response = $client->get('/boom');
$this->assertEquals(500, $response->getStatusCode());
$this->assertStringContainsString('Boom', $response->getBody());
```

---

## 🧩 Session Testing

Since `Session` uses the same PHP `$_SESSION` superglobal,
it works normally during tests.

You can check flash messages and user data easily.

```php
use AdaiasMagdiel\Erlenmeyer\Session;

Session::set('user_id', 1);
$this->assertEquals(1, Session::get('user_id'));

Session::flash('notice', 'Profile updated!');
$this->assertEquals('Profile updated!', Session::getFlash('notice'));
```

---

## 🧰 Using PHPUnit or Pest

ErlenClient integrates perfectly with modern PHP testing frameworks.

Example `ExampleTest.php` (PHPUnit):

```php
<?php
use PHPUnit\Framework\TestCase;
use AdaiasMagdiel\Erlenmeyer\Testing\ErlenClient;

final class ExampleTest extends TestCase
{
    private ErlenClient $client;

    protected function setUp(): void
    {
        $app = require __DIR__ . '/bootstrap.php';
        $this->client = new ErlenClient($app);
    }

    public function testHelloRoute(): void
    {
        $response = $this->client->get('/hello');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Hello', $response->getBody());
    }
}
```

Run with:

```bash
vendor/bin/phpunit
```

---

## 🧩 Example: Full API Test Suite

```php
$response = $client->post('/login', [
    'json' => ['email' => 'user@example.com', 'password' => 'secret']
]);
$this->assertEquals(200, $response->getStatusCode());

$response = $client->get('/profile', [
    'headers' => ['Authorization' => 'Bearer token123']
]);
$this->assertEquals(200, $response->getStatusCode());
$this->assertStringContainsString('user@example.com', $response->getBody());
```

---

## 🚀 Summary

| Concept                   | Description                                    |
| ------------------------- | ---------------------------------------------- |
| **ErlenClient**           | Simulates HTTP requests internally             |
| **JSON & Form testing**   | Send JSON, form data, and query strings easily |
| **CORS testing**          | Test preflight OPTIONS requests                |
| **Middleware & Auth**     | Fully executed during tests                    |
| **Session**               | Uses real PHP sessions                         |
| **Framework Integration** | Works with PHPUnit, Pest, or custom scripts    |
