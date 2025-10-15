# Class: `AdaiasMagdiel\Erlenmeyer\Testing\ErlenClient`

**Namespace:** `AdaiasMagdiel\Erlenmeyer\Testing`  
**Defined in:** `app/Testing/ErlenClient.php`

---

## Overview

The `ErlenClient` class provides a lightweight **testing client** for simulating HTTP requests  
directly within an Erlenmeyer application — without starting a web server.

It enables integration testing by emulating full HTTP request cycles and supports  
headers, query strings, JSON bodies, form parameters, and file uploads.

---

## Properties

| Name              | Visibility | Type    | Description                                             |
| ----------------- | ---------- | ------- | ------------------------------------------------------- |
| `$app`            | private    | `App`   | The `App` instance under test.                          |
| `$defaultHeaders` | private    | `array` | Default headers automatically included in all requests. |

---

## Constructor

### `__construct(App $app)`

Creates a new testing client bound to a specific `App` instance.

#### Parameters

| Name   | Type  | Description                                                   |
| ------ | ----- | ------------------------------------------------------------- |
| `$app` | `App` | The application instance that will handle simulated requests. |

---

## Methods

### `withHeaders(array $headers): self`

Adds default headers that will be included in **all subsequent requests**.

#### Parameters

| Name       | Type    | Description                                   |
| ---------- | ------- | --------------------------------------------- |
| `$headers` | `array` | Associative array of header names and values. |

**Returns:** `self`

---

### `resetHeaders(): self`

Clears all default headers previously set via `withHeaders()`.

**Returns:** `self`

---

## HTTP Method Shortcuts

Each of the following methods simulates a specific HTTP verb  
and delegates to `request()` internally.

| Method      | Signature                                             | Description               |
| ----------- | ----------------------------------------------------- | ------------------------- |
| `get()`     | `get(string $uri, array $options = []): Response`     | Sends a GET request.      |
| `post()`    | `post(string $uri, array $options = []): Response`    | Sends a POST request.     |
| `put()`     | `put(string $uri, array $options = []): Response`     | Sends a PUT request.      |
| `patch()`   | `patch(string $uri, array $options = []): Response`   | Sends a PATCH request.    |
| `delete()`  | `delete(string $uri, array $options = []): Response`  | Sends a DELETE request.   |
| `head()`    | `head(string $uri, array $options = []): Response`    | Sends a HEAD request.     |
| `options()` | `options(string $uri, array $options = []): Response` | Sends an OPTIONS request. |

All methods return a [`Response`](../Response.md) instance.

---

### `request(string $method, string $uri, array $options = []): Response`

Executes an HTTP request against the application.  
This method is the **core of the testing client** and powers all HTTP verb shortcuts.

#### Parameters

| Name       | Type     | Description                         |
| ---------- | -------- | ----------------------------------- |
| `$method`  | `string` | HTTP method (GET, POST, PUT, etc.). |
| `$uri`     | `string` | Request URI (absolute or relative). |
| `$options` | `array`  | Optional configuration array:       |

#### Options

| Key           | Type     | Description                                                 |
| ------------- | -------- | ----------------------------------------------------------- |
| `headers`     | `array`  | Custom HTTP headers.                                        |
| `query`       | `array`  | Query string parameters.                                    |
| `json`        | `array`  | Data to encode as JSON (`Content-Type` automatically set).  |
| `form_params` | `array`  | Form data (encoded as `application/x-www-form-urlencoded`). |
| `files`       | `array`  | Simulated uploaded files (mirrors `$_FILES` structure).     |
| `body`        | `string` | Raw body content.                                           |

#### Returns

[`Response`](../Response.md) — The response object returned by the application.

---

### `normalizeUri(string $uri): string` _(private)_

Normalizes a URI path in the same way as `App`.  
Removes trailing slashes except for `/` and preserves query strings and fragments.

#### Parameters

| Name   | Type     | Description       |
| ------ | -------- | ----------------- |
| `$uri` | `string` | URI to normalize. |

**Returns:** `string` – Normalized URI.

---

## Behavior Summary

| Feature                  | Description                                                                 |
| ------------------------ | --------------------------------------------------------------------------- |
| **Server Emulation**     | Simulates `$_SERVER`, `$_GET`, `$_POST`, and `$_FILES` for the request.     |
| **Header Management**    | Merges global and per-request headers automatically.                        |
| **Payload Support**      | Accepts JSON, form, and raw body payloads.                                  |
| **Query Handling**       | Builds and attaches query strings dynamically.                              |
| **Response Integration** | Returns a real `Response` object from the `App`.                            |
| **No Server Required**   | Requests are handled entirely in memory — no sockets or HTTP server needed. |

---

## See Also

- [`App`](../App.md)
- [`Request`](../Request.md)
- [`Response`](../Response.md)
