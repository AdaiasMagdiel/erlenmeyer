# Routing

!!! note "Tradução em andamento"
    Esta página ainda não foi traduzida para pt-BR e está exibindo o conteúdo original em inglês. [Ajude a traduzir](https://github.com/AdaiasMagdiel/Erlenmeyer).


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

1. Call the fallback handler (if registered via `setFallbackHandler()`);
2. Otherwise, call the 404 handler.

You can customize the 404 handler at any time:

```php
$app->set404Handler(function ($req, $res) {
    $res->setStatusCode(404)->withHtml('<h1>Not found</h1>')->send();
});
```

---

## Route Groups

Group related routes under a shared path prefix and middleware set with `group()`:

```php
$app->group('/admin', function () use ($app) {
    $app->get('/users', function ($req, $res) {
        $res->withJson(['users' => []])->send();
    });

    $app->get('/users/[id]', function ($req, $res, $params) {
        $res->withJson(['id' => $params->id])->send();
    });
}, [$authMiddleware]);
```

This registers `GET /admin/users` and `GET /admin/users/[id]`, both running `$authMiddleware`
before the route handler. The prefix and middlewares only apply to routes registered inside
the callback — nothing outside the group is affected.

Groups can be nested; prefixes concatenate and middlewares accumulate from the outermost
group inward:

```php
$app->group('/admin', function () use ($app) {
    $app->group('/reports', function () use ($app) {
        $app->get('/sales', $handler); // -> GET /admin/reports/sales
    }, [$reportsMiddleware]);
}, [$authMiddleware]);
// /admin/reports/sales runs $authMiddleware then $reportsMiddleware, then $handler
```

`redirect()` called inside a group is prefixed the same way as routes.

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

Route matching is handled by the `Router` class and is optimized to avoid scanning every
registered route on each request:

- **Redirects** and **static routes** (no `[param]` segments, e.g. `/users`, `/about`) are
  stored in hash maps keyed by the normalized URI. Matching them is a direct `O(1)` lookup —
  no regex involved.
- **Dynamic routes** (containing `[param]` segments) are compiled to a regex once, at
  registration time, and grouped by their first static URI segment (e.g. everything under
  `/users/...` is bucketed together). On each request, only the bucket matching the incoming
  URI's first segment — plus routes whose first segment is itself dynamic — is scanned with
  `preg_match`, instead of every dynamic route in the app.
- The first candidate whose pattern matches wins; matched groups are mapped into the
  `$params` object passed to your handler.
- If nothing matches, the fallback handler runs if registered, otherwise the 404 handler.

This keeps lookups fast without needing a general-purpose routing tree, which fits
Erlenmeyer's goal of staying small and easy to reason about.
