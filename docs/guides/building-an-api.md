# Building an API

Erlenmeyer makes it simple to build **clean, lightweight, and fully structured APIs**  
— with routes, middleware, error handling, and JSON responses — all in plain PHP.

This guide walks you through creating a basic RESTful API step by step.

---

## 🧩 Project Setup

Create a new project folder:

```bash
mkdir my-api
cd my-api
```

Install Erlenmeyer via Composer:

```bash
composer require adaiasmagdiel/erlenmeyer
```

Create the main entry point:

```
my-api/
└── public/
    └── index.php
```

---

## ⚙️ Basic Application

Start by creating a minimal API that returns a JSON response.

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

$app = new App();

$app->get('/hello', function (Request $req, Response $res, stdClass $params): Response {
    return $res->withJson(['message' => 'Hello, API!']);
});

$app->run();
```

Now run the built-in PHP server:

```bash
php -S localhost:8000 -t public
```

Visit:
👉 [http://localhost:8000/hello](http://localhost:8000/hello)

You should see:

```json
{ "message": "Hello, API!" }
```

---

## 🧱 Defining Routes

Erlenmeyer supports all common HTTP verbs:

```php
$app->get('/users', $handler);
$app->post('/users', $handler);
$app->put('/users/[id]', $handler);
$app->delete('/users/[id]', $handler);
```

### Example

```php
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

$users = [
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
];

$app->get('/users', function (Request $req, Response $res, stdClass $params): Response {
    global $users;
    return $res->withJson($users);
});

$app->get('/users/[id]', function (Request $req, Response $res, stdClass $params): Response {
    global $users;

    $id = (int) $params->id;
    $user = array_values(array_filter($users, fn($u) => $u['id'] === $id))[0] ?? null;

    if (!$user) {
        return $res->withJson(['error' => 'User not found'])
                   ->setStatusCode(404);
    }

    return $res->withJson($user);
});
```

---

## 🧩 Handling JSON Requests

To read JSON input from the client:

```php
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

$app->post('/users', function (Request $req, Response $res, stdClass $params): Response {
    $data = $req->getJson(true);

    if (!$data || empty($data['name'])) {
        return $res->withJson(['error' => 'Invalid input'])
                   ->setStatusCode(400);
    }

    return $res->withJson([
        'message' => 'User created successfully',
        'user' => $data,
    ])->setStatusCode(201);
});
```

---

## 🧠 Using Middleware

Middleware can handle cross-cutting concerns such as authentication or logging.

```php
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

$app->addMiddleware(function (Request $req, Response $res, callable $next, stdClass $params): void {
    $token = $req->getHeader('Authorization');

    if ($token !== 'Bearer secret123') {
        $res->withJson(['error' => 'Unauthorized'])
            ->setStatusCode(401)
            ->send();
        return;
    }

    $next($req, $res, $params);
});
```

This will run before every route — you can also apply it to specific routes if needed.

---

## 🧰 Error Handling

Erlenmeyer lets you define custom error handlers for exceptions or HTTP 404s.

```php
use Throwable;

$app->set404Handler(function (Request $req, Response $res) {
    $res->withJson(['error' => 'Endpoint not found'])
        ->setStatusCode(404)
        ->send();
});

$app->setExceptionHandler(Throwable::class, function (Request $req, Response $res, Throwable $e) {
    $res->withJson([
        'error' => 'Server error',
        'message' => $e->getMessage(),
    ])->setStatusCode(500)->send();
});
```

---

## 🧪 Testing the API

You can use **ErlenClient** to test your API routes programmatically.

```php
use AdaiasMagdiel\Erlenmeyer\ErlenClient;

$client = new ErlenClient($app);

$response = $client->get('/users');
echo $response->getBody();
```

This simulates HTTP requests without running a web server — perfect for automated testing.

---

## 🧩 Full Example

Here’s a small but complete API that supports listing, adding, and fetching users:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

$app = new App();

$users = [
    ['id' => 1, 'name' => 'Alice'],
    ['id' => 2, 'name' => 'Bob'],
];

$app->get('/users', function (Request $req, Response $res, stdClass $params): Response {
    global $users;
    return $res->withJson($users);
});

$app->get('/users/[id]', function (Request $req, Response $res, stdClass $params): Response {
    global $users;
    $id = (int) $params->id;
    $user = array_values(array_filter($users, fn($u) => $u['id'] === $id))[0] ?? null;

    if (!$user) {
        return $res->withJson(['error' => 'User not found'])
                   ->setStatusCode(404);
    }

    return $res->withJson($user);
});

$app->post('/users', function (Request $req, Response $res, stdClass $params): Response {
    global $users;

    $data = $req->getJson();
    $id = count($users) + 1;

    $user = ['id' => $id, 'name' => $data['name']];
    $users[] = $user;

    return $res->withJson([
        'message' => 'User added',
        'user' => $user,
    ])->setStatusCode(201);
});

$app->run();
```

Run it, then test with:

```bash
curl http://localhost:8000/users
curl http://localhost:8000/users/1
curl -X POST -H "Content-Type: application/json" \
     -d '{"name": "Charlie"}' http://localhost:8000/users
```

---

## 🚀 Summary

| Concept        | Description                                 |
| -------------- | ------------------------------------------- |
| **Routes**     | Define endpoints for GET, POST, PUT, DELETE |
| **JSON**       | Use `withJson()` for structured responses   |
| **Requests**   | Parse body with `$req->getJson()`           |
| **Middleware** | Handle authentication, logging, etc.        |
| **Errors**     | Customize 404 and exception handlers        |
| **Testing**    | Use `ErlenClient` to simulate API calls     |

---

!!! tip "Keep it modular"
	For larger APIs, organize your routes into separate files or controllers —
	Erlenmeyer is flexible enough to scale cleanly without adding complexity.
