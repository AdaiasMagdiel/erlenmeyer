# Class: `AdaiasMagdiel\Erlenmeyer\Router`

**Namespace:** `AdaiasMagdiel\Erlenmeyer`  
**Defined in:** `app/Router.php`

---

## Overview

The `Router` class handles **route registration and matching** independently from the `App` class.

It was extracted from `App` in v5.0.0 to maintain a clean separation of concerns.
The `App` class instantiates and owns the router internally — you do not create `Router` directly.

Route definitions registered via `$app->get()`, `$app->post()`, etc. are delegated to this class.

---

## Properties

| Name             | Visibility | Type              | Description                                      |
| ---------------- | ---------- | ----------------- | ------------------------------------------------ |
| `$routes`        | private    | `array`           | Registered routes, grouped by HTTP method.       |
| `$redirects`     | private    | `array`           | Registered redirect rules.                       |
| `$routePattern`  | private    | `string`          | Regex to detect dynamic parameters in routes.    |
| `$paramPattern`  | private    | `string`          | Replacement pattern for parameter capture groups.|

---

## Constructor

### `__construct()`

Creates a new `Router` instance. Called internally by `App`. Takes no parameters.

---

## Public Methods

### `add(string $method, string $route, callable $action, array $middlewares = []): void`

Registers a route handler for a given HTTP method and route pattern.

| Parameter      | Type       | Description                                                        |
| -------------- | ---------- | ------------------------------------------------------------------ |
| `$method`      | `string`   | HTTP method (`GET`, `POST`, `PUT`, `DELETE`, `PATCH`, `OPTIONS`, `HEAD`). Case-insensitive. |
| `$route`       | `string`   | Route pattern. Supports dynamic segments using `[param]` syntax.   |
| `$action`      | `callable` | Handler `(Request $req, Response $res, stdClass $params): void`.   |
| `$middlewares` | `array`    | Optional list of middlewares specific to this route.               |

**Throws:** `InvalidArgumentException` — if `$method` is not one of the valid HTTP methods.

**Valid methods:** `GET`, `POST`, `PUT`, `DELETE`, `PATCH`, `OPTIONS`, `HEAD`

---

### `redirect(string $from, string $to, bool $permanent = false): void`

Registers an internal redirect rule.

| Parameter    | Type     | Description                                             |
| ------------ | -------- | ------------------------------------------------------- |
| `$from`      | `string` | Source URI (trailing slash removed, except for `/`).    |
| `$to`        | `string` | Target URI.                                             |
| `$permanent` | `bool`   | Use HTTP 301 (`true`) or 302 (`false`, default).        |

---

### `match(string $method, string $uri): ?array`

Tries to match an HTTP method and URI against registered redirects and routes.

Redirects are checked first. Routes are checked second.

| Parameter | Type     | Description              |
| --------- | -------- | ------------------------ |
| `$method` | `string` | HTTP method (uppercase). |
| `$uri`    | `string` | Request URI to match.    |

**Returns:** `array` on match, `null` if no route or redirect matches.

#### Return format — Redirect match

```php
[
    'type'   => 'redirect',
    'to'     => '/new-path',
    'status' => 301, // or 302
]
```

#### Return format — Route match

```php
[
    'type'        => 'route',
    'handler'     => callable,
    'middlewares' => [],
    'params'      => stdClass, // dynamic parameters extracted from the URI
]
```

---

## Route Parameter Syntax

Dynamic route segments are defined using square brackets:

```
/users/[id]          → matches /users/42        → $params->id = "42"
/files/[dir]/[name]  → matches /files/docs/readme → $params->dir = "docs", $params->name = "readme"
```

Parameter names support: letters, digits, dots (`.`), hyphens (`-`), underscores (`_`).

---

## Behavior Notes

- URIs are **normalized**: trailing slashes are stripped (except for the root `/`).
- Route patterns are compiled to regex internally via `parseRoute()`.
- Route registration order matters — first registered, first matched.

---

## See Also

- [`App`](./App.md)
- [`Request`](./Request.md)
- [`Response`](./Response.md)
