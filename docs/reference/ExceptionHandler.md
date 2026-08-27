# Class: `AdaiasMagdiel\Erlenmeyer\Exception\Handler`

**Namespace:** `AdaiasMagdiel\Erlenmeyer\Exception`  
**Defined in:** `app/Exception/Handler.php`

---

## Overview

The `Handler` class manages **exception handler registration and dispatch** in Erlenmeyer.

It was extracted from `App` in v5.0.0 as part of the architecture refactor.
The `App` class instantiates and owns the handler internally — you do not create it directly.

Handlers registered via `$app->setExceptionHandler()` are delegated to this class.

---

## Properties

| Name       | Visibility | Type              | Description                                        |
| ---------- | ---------- | ----------------- | -------------------------------------------------- |
| `$handlers` | private   | `array`           | Map of exception class names to handler closures.  |

---

## Constructor

### `__construct()`

Creates a new `Handler` instance. Called internally by `App`. Takes no parameters.

---

## Public Methods

### `register(string $throwableClass, callable $handler): void`

Registers a handler callable for a specific exception class.

| Parameter         | Type       | Description                                                                        |
| ----------------- | ---------- | ---------------------------------------------------------------------------------- |
| `$throwableClass` | `string`   | Fully-qualified class name. Must implement `Throwable`.                            |
| `$handler`        | `callable` | Handler `(Request $req, Response $res, Throwable $e): void`.                      |

**Throws:** `InvalidArgumentException` — if `$throwableClass` does not implement `Throwable`.

**Logs:** an `INFO` entry when a handler is registered.

#### Example

```php
// Registering via App (recommended)
$app->setExceptionHandler(ValidationException::class, function ($req, $res, $e) {
    $res->setStatusCode(422)->withJson(['error' => $e->getMessage()])->send();
});

// Registering a catch-all
$app->setExceptionHandler(Throwable::class, function ($req, $res, $e) {
    $res->setStatusCode(500)->withJson(['error' => 'Internal server error'])->send();
});
```

---

### `getHandler(Throwable $e): ?Closure`

Returns the most specific registered handler for the given exception.

Resolution order:
1. Exact class match (e.g., `ValidationException`)
2. Parent class traversal (e.g., `RuntimeException` → `Exception` → `Throwable`)
3. Fallback to `Throwable::class` handler if registered

Returns `null` if no matching handler is found.

| Parameter | Type        | Description                |
| --------- | ----------- | -------------------------- |
| `$e`      | `Throwable` | The thrown exception.      |

**Returns:** `?Closure` — the matching handler, or `null`.

---

## Hierarchy Traversal Example

Given:

```php
$app->setExceptionHandler(RuntimeException::class, $handler);
```

When `InvalidArgumentException` (which extends `LogicException` → `Exception` → `Throwable`)
is thrown, the resolver checks:

1. `InvalidArgumentException` — not registered
2. `LogicException` — not registered
3. `Exception` — not registered
4. `Throwable` — falls back to the generic handler if registered

If `RuntimeException::class` were a parent of the thrown exception, it would match at step 2.

---

## Behavior Summary

| Feature              | Description                                                          |
| -------------------- | -------------------------------------------------------------------- |
| **Registration**     | Associates exception classes to handler closures.                    |
| **Resolution**       | Traverses class hierarchy to find the most specific match.           |
| **Validation**       | Rejects classes that don't implement `Throwable`.                    |
| **Fallback**         | Returns `null` when no handler is found (App handles the fallback).  |

---

## See Also

- [`App`](./App.md)
