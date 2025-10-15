# Class: `AdaiasMagdiel\Erlenmeyer\Assets`

**Namespace:** `AdaiasMagdiel\Erlenmeyer`  
**Defined in:** `app/Assets.php`

---

## Overview

The `Assets` class provides utilities to manage and serve **static files** (CSS, JS, images, fonts, etc.)  
through a configurable assets directory and route prefix.  
It handles file validation, MIME detection, caching headers, and HTTP 304 (Not Modified) responses.

---

## Properties

| Name               | Visibility | Type     | Description                                                     |
| ------------------ | ---------- | -------- | --------------------------------------------------------------- |
| `$assetsDirectory` | private    | `string` | Filesystem directory where static assets are stored.            |
| `$assetsRoute`     | private    | `string` | Route prefix used to identify asset requests (e.g., `/assets`). |

---

## Constructor

### `__construct(string $assetsDirectory = "/public", string $assetsRoute = "/assets")`

Creates a new `Assets` manager instance.

#### Parameters

| Name               | Type     | Description                                        |
| ------------------ | -------- | -------------------------------------------------- |
| `$assetsDirectory` | `string` | Path to the directory containing asset files.      |
| `$assetsRoute`     | `string` | Route prefix for asset requests (e.g., `/assets`). |

#### Throws

- `InvalidArgumentException` – If the directory does not exist, is unreadable, or if the route is invalid.

---

## Public Methods

### `getAssetsDirectory(): string`

Returns the absolute directory path where assets are stored.

#### Returns

`string` – The asset storage directory path.

---

### `getAssetsRoute(): string`

Returns the route prefix for serving assets.

#### Returns

`string` – The route prefix (always prefixed with `/`).

---

### `isAssetRequest(Request $req): bool`

Determines whether the current request targets the asset route.

#### Parameters

| Name   | Type      | Description                        |
| ------ | --------- | ---------------------------------- |
| `$req` | `Request` | The current HTTP request instance. |

#### Returns

`bool` – `true` if the request URI begins with the assets route prefix; otherwise `false`.

---

### `serveAsset(Request $req): bool`

Serves the requested asset file to the client.

This method:

- Validates the request path.
- Checks for the file’s existence.
- Ensures the requested path stays inside the configured assets directory.
- Sends the file with proper headers.
- Handles conditional requests (`If-None-Match`, `If-Modified-Since`).

#### Parameters

| Name   | Type      | Description                        |
| ------ | --------- | ---------------------------------- |
| `$req` | `Request` | The current HTTP request instance. |

#### Returns

`bool` – `true` if the asset was found and served successfully, otherwise `false`.

---

### `detectMimeType(string $filePath): string`

Detects the appropriate MIME type for a given file based on its extension.

#### Parameters

| Name        | Type     | Description       |
| ----------- | -------- | ----------------- |
| `$filePath` | `string` | Path to the file. |

#### Returns

`string` – MIME type corresponding to the file extension, or `application/octet-stream` as fallback.

---

## Private Methods

### `isValidAsset(string $path): bool`

Checks whether a given path points to a valid file.

#### Parameters

| Name    | Type     | Description            |
| ------- | -------- | ---------------------- |
| `$path` | `string` | File path to validate. |

#### Returns

`bool` – `true` if the path exists and is a regular file.

---

### `sendFileToClient(string $filePath): void`

Outputs the specified file to the HTTP response with appropriate headers.

Headers include:

- `Content-Type`
- `Content-Length`
- `Cache-Control`
- `ETag`
- `Last-Modified`

Supports **browser caching** via:

- `If-None-Match` (ETag)
- `If-Modified-Since`

If caching conditions are met, responds with **304 Not Modified**.

#### Parameters

| Name        | Type     | Description                    |
| ----------- | -------- | ------------------------------ |
| `$filePath` | `string` | Full path to the file to send. |

---

## Behavior Summary

| Feature              | Description                                                  |
| -------------------- | ------------------------------------------------------------ |
| Directory Validation | Ensures asset directory exists and is readable.              |
| Route Detection      | Checks if requests match the configured asset route.         |
| Security             | Prevents directory traversal outside of asset root.          |
| MIME Detection       | Determines MIME type via file extension.                     |
| HTTP Caching         | Supports ETag and Last-Modified headers for browser caching. |
| Error Handling       | Returns 400, 404, or 500 when appropriate.                   |

---

## See Also

- [`App`](./App.md)
- [`Request`](./Request.md)
- [`Response`](./Response.md)
