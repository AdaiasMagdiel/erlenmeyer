# Handling CORS and Authentication

Erlenmeyer makes it easy to handle **CORS (Cross-Origin Resource Sharing)**  
and **authentication** through **global middleware** — giving you full control  
over access, headers, and user data at a single entry point.

---

## 🧩 Why Global Middleware?

A **global middleware** in Erlenmeyer runs **before all routes**, even if a route  
has not been explicitly registered.

This means you can:

- Respond to **`OPTIONS` preflight requests** automatically (perfect for CORS).
- Inject **authenticated user data** into `$params` before any route handler executes.

That’s what makes Erlenmeyer’s middleware system both flexible and powerful.

---

## ⚙️ Enabling CORS with Global Middleware

Let’s start with **CORS** — Cross-Origin Resource Sharing.

Browsers send an `OPTIONS` request automatically to check whether  
the target domain allows the main request.

With a **global middleware**, you can intercept _every_ request — including `OPTIONS` —  
and handle CORS headers without registering separate routes.

### Example

```php
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

$app->addMiddleware(function (Request $req, Response $res, callable $next, stdClass $params): void {
    // Apply CORS headers to every request
    $res->setCORS([
        'origin' => '*',
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'headers' => ['Content-Type', 'Authorization'],
        'credentials' => false,
        'max_age' => 86400, // cache preflight response for 1 day
    ]);

    // Handle preflight requests automatically
    if ($req->getMethod() === 'OPTIONS') {
        $res->setStatusCode(204)->send();
        return;
    }

    // Continue to next middleware or route
    $next($req, $res, $params);
});
```

### ✅ Key Advantages

- Works even if no route is defined for `OPTIONS`.
- Runs before assets, 404, or any route handler.
- Keeps your route definitions clean and focused.

---

## 🔒 Authentication Middleware

Now, let’s handle **authentication**.

You can use another global middleware (or combine both in one)
to check for a token, validate it, and inject a user object into `$params`.

That way, every route automatically receives the current authenticated user.

### Example: Bearer Token Auth

```php
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

$app->addMiddleware(function (Request $req, Response $res, callable $next, stdClass $params): void {
    $authHeader = $req->getHeader('Authorization');

    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        $res->withJson(['error' => 'Missing or invalid Authorization header'])
            ->setStatusCode(401)
            ->send();
        return;
    }

    $token = substr($authHeader, 7);

    // Simulate token validation (replace with your logic)
    $user = match ($token) {
        'secret123' => (object) ['id' => 1, 'name' => 'Alice'],
        'admin456'  => (object) ['id' => 2, 'name' => 'Bob', 'role' => 'admin'],
        default     => null,
    };

    if (!$user) {
        $res->withJson(['error' => 'Unauthorized'])
            ->setStatusCode(401)
            ->send();
        return;
    }

    // Inject the user into route parameters
    $params->user = $user;

    // Continue with the request
    $next($req, $res, $params);
});
```

Now every route can access the authenticated user via `$params->user`.

---

## 🧱 Example: Protected Routes

```php
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

$app->get('/profile', function (Request $req, Response $res, stdClass $params): Response {
    if (empty($params->user)) {
        return $res->withJson(['error' => 'Not authenticated'])->setStatusCode(401);
    }

    return $res->withJson([
        'id' => $params->user->id,
        'name' => $params->user->name,
    ]);
});
```

This route will only respond successfully if `$params->user` was
injected by your authentication middleware.

---

## 🧩 Combining CORS and Auth

You can easily combine both middlewares — order matters!

1. The **CORS middleware** should run first (to handle preflight and headers).
2. The **auth middleware** should run second (to check tokens only for valid requests).

### Example

```php
// 1️⃣ CORS middleware
$app->addMiddleware(function (Request $req, Response $res, callable $next, stdClass $params): void {
    $res->setCORS([
        'origin' => '*',
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'headers' => ['Content-Type', 'Authorization'],
    ]);

    if ($req->getMethod() === 'OPTIONS') {
        $res->setStatusCode(204)->send();
        return;
    }

    $next($req, $res, $params);
});

// 2️⃣ Auth middleware
$app->addMiddleware(function (Request $req, Response $res, callable $next, stdClass $params): void {
    $authHeader = $req->getHeader('Authorization');

    if (!$authHeader) {
        $res->withJson(['error' => 'Unauthorized'])->setStatusCode(401)->send();
        return;
    }

    $params->user = (object) ['id' => 1, 'name' => 'ExampleUser']; // Example
    $next($req, $res, $params);
});
```

This ensures that all responses have proper CORS headers
and that authenticated routes can safely access `$params->user`.

---

## 🧪 Testing with ErlenClient

You can simulate preflight and authenticated requests easily:

```php
use AdaiasMagdiel\Erlenmeyer\Testing\ErlenClient;

$client = new ErlenClient($app);

// Test CORS preflight
$response = $client->options('/profile');
echo $response->getStatusCode(); // 204

// Test authenticated GET
$response = $client->get('/profile', [
    'headers' => ['Authorization' => 'Bearer secret123']
]);
echo $response->getBody();
```

Output:

```json
{
  "id": 1,
  "name": "Alice"
}
```

---

## 🚀 Summary

| Concept                  | Description                                         |
| ------------------------ | --------------------------------------------------- |
| **Global middleware**    | Runs before all routes, even unregistered ones      |
| **CORS handling**        | Handles OPTIONS and adds CORS headers automatically |
| **Auth middleware**      | Validates tokens and injects `$params->user`        |
| **Combined middlewares** | Handle cross-origin and authentication together     |
| **Testable**             | Works perfectly with `ErlenClient`                  |
