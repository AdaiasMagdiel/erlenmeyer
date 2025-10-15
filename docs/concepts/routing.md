# Routing

Routing in Erlenmeyer follows the same philosophy as the rest of the framework: **simplicity and clarity first**.  
You declare routes directly on the `App` instance — no separate route files or rigid structures required.

---

## How routing works

At its core, routing is managed by the [`App`](../reference/App.md) class.  
Each route maps an HTTP method and a URI pattern to a callable handler:

```php
$app->get('/hello', function (Request $req, Response $res) {
    $res->withText('Hello world!')->send();
});
```

Under the hood, routes are stored internally as regular expressions, allowing Erlenmeyer to support dynamic parameters and even fallback routes.

---

## Dynamic parameters

Parameters are enclosed in square brackets `[]`, and are automatically converted into named regex groups:

```php
$app->get('/users/[id]', function ($req, $res, $params) {
    $res->withJson(['id' => $params->id])->send();
});
```

You can use as many parameters as you need, and Erlenmeyer will map them into a `$params` object:

```php
$app->get('/posts/[year]/[slug]', fn($req, $res, $p)
    => $res->withJson($p)->send());
```

Internally, `/users/[id]` becomes:

```
/^\/users\/([a-zA-Z0-9\.\-_]+)$/
```

---

## Route methods

You can register routes for any HTTP verb, or for multiple ones at once:

```php
$app->get('/users', ...);
$app->post('/users', ...);
$app->put('/users/[id]', ...);
$app->delete('/users/[id]', ...);
$app->patch('/users/[id]', ...);
```

Or combine several in one call:

```php
$app->match(['GET', 'POST'], '/contact', ...);
$app->any('/ping', ...); // handles all methods
```

---

## Redirects

Simple route redirection is built in:

```php
$app->redirect('/old-home', '/new-home');
$app->redirect('/legacy', '/', permanent: true);
```

The second parameter defines the destination, and the optional `permanent` flag triggers a 301 redirect instead of 302.

---

## Fallbacks and 404s

If no route matches, Erlenmeyer will:

1. Try to serve a static file through the `Assets` manager (if configured);
2. Otherwise, call the 404 handler.

You can customize the 404 handler at any time:

```php
$app->set404Handler(function ($req, $res) {
    $res->setStatusCode(404)->withHtml('<h1>Not found</h1>')->send();
});
```

---

## Middlewares

Each route can have its own middleware chain.
A middleware receives a `$next()` callback to continue the execution flow:

```php
$auth = function ($req, $res, $next, $params) {
    if ($req->getQueryParam('token') !== 'secret') {
        $res->withText('Unauthorized')->setStatusCode(401)->send();
        return;
    }
    $next($req, $res, new stdClass());
};

$app->get('/secure', fn($req, $res) => $res->withText('Welcome!')->send(), [$auth]);
```

You can also register **global middlewares**:

```php
$app->addMiddleware($auth);
```

They will be applied to **every** route automatically.

---

## Under the hood

- The `App` class keeps an internal map of routes by method.
- Each route is stored as a regex pattern and a handler.
- When a request arrives, `handle()` loops through the registered routes, testing each pattern with `preg_match`.
- If a match is found, parameters are mapped into an object and passed to the handler.
- If none match, the fallback or 404 handler is invoked.

This simple mechanism allows Erlenmeyer to remain lightweight, predictable, and easy to debug.
