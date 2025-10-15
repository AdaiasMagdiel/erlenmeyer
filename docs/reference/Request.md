# Class: `AdaiasMagdiel\Erlenmeyer\Request`

**Namespace:** `AdaiasMagdiel\Erlenmeyer`  
**Defined in:** `app/Request.php`

---

## Overview

The `Request` class represents an HTTP request and provides access to all request-related data,  
including headers, URI, method, query parameters, POST form data, JSON body, uploaded files,  
and client information such as IP address and User-Agent.

This class is used internally by the `App` during route dispatching and can be used  
for manual request handling and testing.

---

## Properties

| Name           | Visibility | Type      | Description                                 |
| -------------- | ---------- | --------- | ------------------------------------------- |
| `$headers`     | private    | `array`   | HTTP request headers.                       |
| `$method`      | private    | `string`  | HTTP method (e.g., `GET`, `POST`, etc.).    |
| `$uri`         | private    | `string`  | Normalized URI path (without query string). |
| `$queryParams` | private    | `array`   | Parsed query parameters.                    |
| `$formData`    | private    | `array`   | POST form data.                             |
| `$jsonData`    | private    | `?array`  | Lazily decoded JSON body data.              |
| `$jsonError`   | private    | `?string` | JSON decoding error message (if any).       |
| `$rawBody`     | private    | `?string` | Raw request body content.                   |
| `$files`       | private    | `array`   | Uploaded file data (`$_FILES`).             |
| `$ip`          | private    | `?string` | Client IP address.                          |
| `$userAgent`   | private    | `?string` | Client User-Agent string.                   |
| `$server`      | private    | `array`   | Server environment data (`$_SERVER`).       |

---

## Constructor

### `__construct(?array $server = null, ?array $get = null, ?array $post = null, ?array $files = null, string $inputStream = 'php://input', ?string $rawBody = null)`

Creates a new `Request` instance, optionally using custom superglobals  
(useful for testing or CLI simulation).

#### Parameters

| Name           | Type      | Description                                   |
| -------------- | --------- | --------------------------------------------- |
| `$server`      | `?array`  | Server array (usually `$_SERVER`).            |
| `$get`         | `?array`  | Query string parameters (`$_GET`).            |
| `$post`        | `?array`  | POST data (`$_POST`).                         |
| `$files`       | `?array`  | Uploaded files (`$_FILES`).                   |
| `$inputStream` | `string`  | Input stream path (default: `'php://input'`). |
| `$rawBody`     | `?string` | Optional raw body data override.              |

---

## Public Methods

### `getHeader(string $name): ?string`

Retrieves a header value by name (case-insensitive).

| Parameter | Type     | Description  |
| --------- | -------- | ------------ |
| `$name`   | `string` | Header name. |

**Returns:** `?string` – Header value or `null` if not found.

---

### `getHeaders(): array`

Returns all HTTP request headers.

**Returns:** `array` – Associative array of all headers.

---

### `hasHeader(string $name): bool`

Checks whether a specific header exists.

| Parameter | Type     | Description  |
| --------- | -------- | ------------ |
| `$name`   | `string` | Header name. |

**Returns:** `bool` – `true` if header exists, otherwise `false`.

---

### `getMethod(): string`

Returns the HTTP request method.  
Supports `_method` override via POST or `X-HTTP-Method-Override` header.

**Returns:** `string` – HTTP method (e.g., `GET`, `POST`, `PUT`, etc.).

---

### `getUri(): string`

Returns the normalized request URI path (without query string).

**Returns:** `string` – URI path.

---

### `getQueryParams(): array`

Returns all query string parameters.

**Returns:** `array` – Associative array of query parameters.

---

### `getQueryParam(string $key, mixed $default = null): mixed`

Retrieves a specific query parameter.

| Parameter  | Type     | Description                 |
| ---------- | -------- | --------------------------- |
| `$key`     | `string` | Parameter name.             |
| `$default` | `mixed`  | Default value if not found. |

**Returns:** `mixed` – Parameter value or default.

---

### `getFormData(): array`

Returns all POST form data.

**Returns:** `array` – Associative array of form fields.

---

### `getFormDataParam(string $key, mixed $default = null): mixed`

Retrieves a specific POST form value.

| Parameter  | Type     | Description                 |
| ---------- | -------- | --------------------------- |
| `$key`     | `string` | Form field key.             |
| `$default` | `mixed`  | Default value if not found. |

**Returns:** `mixed` – Form value or default.

---

### `getJson(bool $assoc = true, bool $ignoreContentType = false): mixed`

Decodes and returns the JSON body of the request.

#### Behavior

- Automatically validates `Content-Type: application/json` unless `$ignoreContentType` is `true`.
- Returns decoded array or object depending on `$assoc`.
- Throws exception if decoding fails or content type is invalid.

| Parameter            | Type   | Description                                                       |
| -------------------- | ------ | ----------------------------------------------------------------- |
| `$assoc`             | `bool` | Whether to return associative array (`true`) or object (`false`). |
| `$ignoreContentType` | `bool` | Whether to ignore content-type validation.                        |

**Returns:** `mixed` – Parsed JSON data (array or object).  
**Throws:** `RuntimeException` – On decoding errors or invalid `Content-Type`.

---

### `getJsonError(): ?string`

Returns the last JSON decoding error message, if any.

**Returns:** `?string` – Error message or `null`.

---

### `getRawBody(): ?string`

Returns the raw request body string.

**Returns:** `?string` – Raw body or `null` if empty.

---

### `getFiles(): array`

Returns all uploaded files.

**Returns:** `array` – Same structure as PHP’s `$_FILES`.

---

### `getFile(string $key, ?int $index = null): ?array`

Returns an uploaded file’s metadata by key (and index for multiple uploads).

| Parameter | Type     | Description                            |
| --------- | -------- | -------------------------------------- |
| `$key`    | `string` | File key.                              |
| `$index`  | `?int`   | Optional index for multi-file uploads. |

**Returns:** `?array` – File info (`name`, `type`, `tmp_name`, `error`, `size`) or `null`.

---

### `getIp(): ?string`

Returns the detected client IP address.

**Returns:** `?string` – IP address or `null`.

---

### `getUserAgent(): ?string`

Returns the client’s User-Agent string.

**Returns:** `?string` – User-Agent or `null`.

---

### `isAjax(): bool`

Checks whether the request is an AJAX request  
(`X-Requested-With: XMLHttpRequest`).

**Returns:** `bool` – `true` if AJAX, otherwise `false`.

---

### `isSecure(): bool`

Checks whether the request was made via HTTPS.

**Returns:** `bool` – `true` if secure (HTTPS), otherwise `false`.

---

## Behavior Summary

| Feature              | Description                                                             |
| -------------------- | ----------------------------------------------------------------------- |
| **Header Parsing**   | Extracts headers from `$_SERVER` keys and normalizes names.             |
| **Method Detection** | Supports overrides via `_method` field or `X-HTTP-Method-Override`.     |
| **JSON Handling**    | Safely decodes body JSON with detailed error reporting.                 |
| **Security**         | Sanitizes query and form input to prevent injection.                    |
| **Client Info**      | Detects IP, User-Agent, and proxy-forwarded addresses.                  |
| **Testing Support**  | Constructor allows custom `$_SERVER`, `$_GET`, `$_POST`, and `$_FILES`. |

---

## See Also

- [`Response`](./Response.md)
- [`App`](./App.md)
- [`Assets`](./Assets.md)
