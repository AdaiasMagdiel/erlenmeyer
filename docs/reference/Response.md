# Class: `AdaiasMagdiel\Erlenmeyer\Response`

**Namespace:** `AdaiasMagdiel\Erlenmeyer`  
**Defined in:** `app/Response.php`

---

## Overview

The `Response` class encapsulates HTTP response handling for Erlenmeyer applications.  
It provides structured methods for setting headers, status codes, and body content,  
including HTML, JSON, plain text, files, and templates.

It also supports CORS configuration, redirects, cookies, and safe send state tracking.

---

## Properties

| Name           | Visibility     | Type                    | Description                                                           |
| -------------- | -------------- | ----------------------- | --------------------------------------------------------------------- |
| `$statusCode`  | private        | `int`                   | HTTP status code (default: `200`).                                    |
| `$headers`     | private        | `array`                 | Response headers.                                                     |
| `$body`        | private        | `?string`               | Response body content.                                                |
| `$isSent`      | private        | `bool`                  | Indicates if the response has already been sent.                      |
| `$contentType` | private        | `string`                | Current MIME type (default: `text/html`).                             |
| `$functions`   | private static | `array<string, string>` | Map of native PHP functions used internally, replaceable for testing. |

---

## Constructor

### `__construct(int $statusCode = 200, array $headers = [])`

Initializes a new `Response` instance.

#### Parameters

| Name          | Type    | Description                                |
| ------------- | ------- | ------------------------------------------ |
| `$statusCode` | `int`   | Initial HTTP status code (default: `200`). |
| `$headers`    | `array` | Optional initial headers.                  |

---

## Static Methods

### `updateFunctions(array $functions = []): void`

Overrides internal function mappings used by the class (e.g., `header()`).

#### Parameters

| Name         | Type    | Description                                                     |
| ------------ | ------- | --------------------------------------------------------------- |
| `$functions` | `array` | Associative array of function replacements. Useful for testing. |

---

## Public Methods

### `setStatusCode(int $code): self`

Sets the HTTP status code.

#### Parameters

| Name    | Type  | Description                 |
| ------- | ----- | --------------------------- |
| `$code` | `int` | HTTP status code (100–599). |

#### Returns

`self`

#### Throws

`InvalidArgumentException` – If the code is out of valid HTTP range.

---

### `getStatusCode(): int`

Returns the current HTTP status code.

**Returns:** `int`

---

### `setHeader(string $name, string $value): self`

Sets or replaces an HTTP header.

#### Parameters

| Name     | Type     | Description   |
| -------- | -------- | ------------- |
| `$name`  | `string` | Header name.  |
| `$value` | `string` | Header value. |

**Returns:** `self`  
**Throws:** `RuntimeException` – If headers were already sent.

---

### `removeHeader(string $name): self`

Removes a header from the response.

#### Parameters

| Name    | Type     | Description  |
| ------- | -------- | ------------ |
| `$name` | `string` | Header name. |

**Returns:** `self`  
**Throws:** `RuntimeException` – If headers were already sent.

---

### `getHeaders(): array`

Returns all response headers.

**Returns:** `array`

---

### `setContentType(string $contentType): self`

Sets the MIME type of the response and updates the `Content-Type` header.

#### Parameters

| Name           | Type     | Description                                        |
| -------------- | -------- | -------------------------------------------------- |
| `$contentType` | `string` | MIME type (e.g., `text/html`, `application/json`). |

**Returns:** `self`

---

### `getContentType(): string`

Returns the current content type.

**Returns:** `string`

---

### `setBody(string $body): self`

Sets the raw response body.

#### Parameters

| Name    | Type     | Description    |
| ------- | -------- | -------------- |
| `$body` | `string` | Response body. |

**Returns:** `self`

---

### `getBody(): ?string`

Returns the current response body.

**Returns:** `?string`

---

### `withHtml(string $html): self`

Sets an HTML response body and content type.

#### Parameters

| Name    | Type     | Description  |
| ------- | -------- | ------------ |
| `$html` | `string` | HTML markup. |

**Returns:** `self`

---

### `withTemplate(string $templatePath, array $data = []): self`

Renders a PHP template file as the response body.

#### Parameters

| Name            | Type     | Description                             |
| --------------- | -------- | --------------------------------------- |
| `$templatePath` | `string` | Path to the template file.              |
| `$data`         | `array`  | Variables passed to the template scope. |

**Returns:** `self`  
**Throws:** `RuntimeException` – If the template file cannot be found.

---

### `withJson(mixed $data, int $options = JSON_PRETTY_PRINT): self`

Sets a JSON response body and content type.

#### Parameters

| Name       | Type    | Description                                                 |
| ---------- | ------- | ----------------------------------------------------------- |
| `$data`    | `mixed` | Data to encode as JSON.                                     |
| `$options` | `int`   | Options for `json_encode()` (default: `JSON_PRETTY_PRINT`). |

**Returns:** `self`  
**Throws:** `RuntimeException` – If JSON encoding fails.

---

### `withText(string $text): self`

Sets a plain text response.

#### Parameters

| Name    | Type     | Description         |
| ------- | -------- | ------------------- |
| `$text` | `string` | Plain text content. |

**Returns:** `self`

---

### `redirect(string $url, int $statusCode = 302): self`

Performs an HTTP redirect by setting `Location` and clearing the body.

#### Parameters

| Name          | Type     | Description                                 |
| ------------- | -------- | ------------------------------------------- |
| `$url`        | `string` | Target URL.                                 |
| `$statusCode` | `int`    | Redirect status code (3xx). Default: `302`. |

**Returns:** `self`  
**Throws:** `InvalidArgumentException` – If status code is not in the 3xx range.

---

### `withCookie(string $name, string $value, int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httpOnly = true): self`

Adds a `Set-Cookie` header to the response.

#### Parameters

| Name        | Type     | Description                                  |
| ----------- | -------- | -------------------------------------------- |
| `$name`     | `string` | Cookie name.                                 |
| `$value`    | `string` | Cookie value.                                |
| `$expire`   | `int`    | Expiration timestamp (0 for session cookie). |
| `$path`     | `string` | Path for which the cookie is valid.          |
| `$domain`   | `string` | Cookie domain.                               |
| `$secure`   | `bool`   | Send only over HTTPS if `true`.              |
| `$httpOnly` | `bool`   | Prevent JavaScript access if `true`.         |

**Returns:** `self`

---

### `send(): void`

Sends the HTTP response to the client, including headers and body.

**Throws:**

- `RuntimeException` – If headers are already sent.
- `RuntimeException` – If the response has already been sent.

---

### `isSent(): bool`

Returns whether the response has already been sent.

**Returns:** `bool`

---

### `clear(): self`

Clears all headers and body while keeping the current status code.

**Returns:** `self`  
**Throws:** `RuntimeException` – If response has already been sent.

---

### `withError(int $statusCode, string $message = '', ?callable $logger = null): self`

Creates an error response with optional logging.

#### Parameters

| Name          | Type        | Description                                              |
| ------------- | ----------- | -------------------------------------------------------- |
| `$statusCode` | `int`       | HTTP status code.                                        |
| `$message`    | `string`    | Optional error message.                                  |
| `$logger`     | `?callable` | Optional logger function `(int $code, string $message)`. |

**Returns:** `self`

---

### `withFile(string $filePath): self`

Sends a file as a downloadable attachment.

#### Parameters

| Name        | Type     | Description         |
| ----------- | -------- | ------------------- |
| `$filePath` | `string` | Absolute file path. |

**Returns:** `self`  
**Throws:** `RuntimeException` – If the file is not readable.

---

### `setCORS(array $options): self`

Configures **Cross-Origin Resource Sharing (CORS)** headers.

#### Options

| Key           | Type                 | Description                   |
| ------------- | -------------------- | ----------------------------- |
| `origin`      | `string \| string[]` | Allowed origin(s).            |
| `methods`     | `string \| string[]` | Allowed HTTP methods.         |
| `headers`     | `string \| string[]` | Allowed request headers.      |
| `credentials` | `bool`               | Whether to allow credentials. |
| `max_age`     | `int`                | Cache duration (in seconds).  |

**Returns:** `self`  
**Throws:** `RuntimeException` – If the response has already been sent.

---

## Behavior Summary

| Feature             | Description                                                  |
| ------------------- | ------------------------------------------------------------ |
| **Status Handling** | Validates HTTP status codes and enforces range 100–599.      |
| **Header Safety**   | Prevents modification after the response is sent.            |
| **Content Helpers** | Supports HTML, JSON, text, files, and templates.             |
| **Cookies**         | Simplified cookie management via headers.                    |
| **CORS**            | Easily configure cross-origin headers.                       |
| **Redirects**       | Sets standard 3xx redirects with URL validation.             |
| **Send Protection** | Ensures responses are sent exactly once.                     |
| **Testing**         | Supports overriding core PHP I/O functions for unit testing. |

---

## See Also

- [`Request`](./Request.md)
- [`App`](./App.md)
- [`Assets`](./Assets.md)
