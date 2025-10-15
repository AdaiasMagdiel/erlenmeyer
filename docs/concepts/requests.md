# Handling Requests

Erlenmeyer’s `Request` object gives you a clean, expressive way  
to interact with incoming HTTP requests — from query strings and forms  
to JSON payloads, uploaded files, and headers.

---

## Accessing the Request

Every route in Erlenmeyer receives the request object as the first argument:

```php
$app->get('/hello', function (Request $req, Response $res) {
    $name = $req->getQueryParam('name', 'world');
    $res->withText("Hello, {$name}!")->send();
});
```

Here, `/hello?name=Adaias` responds with:

```
Hello, Adaias!
```

And `/hello` responds with:

```
Hello, world!
```

---

## Reading Query and Form Data

```php
$name = $req->getQueryParam('name');   // ?name=John
$email = $req->getFormDataParam('email'); // POST form input
```

You can also get all parameters at once:

```php
$req->getQueryParams(); // returns array
$req->getFormData();    // returns array
```

---

## Working with JSON

Erlenmeyer automatically decodes JSON payloads when the `Content-Type`
header is `application/json`.

```php
$app->post('/api/data', function (Request $req, Response $res) {
    $data = $req->getJson();
    $res->withJson(['received' => $data])->send();
});
```

If the request body isn’t valid JSON, you can safely handle it:

```php
if ($req->getJsonError()) {
    $res->withError(400, "Invalid JSON: " . $req->getJsonError())->send();
    return;
}
```

---

## Headers and Metadata

```php
$agent = $req->getHeader('User-Agent');
$all   = $req->getHeaders();

if ($req->isSecure()) {
    // Request made over HTTPS
}

if ($req->isAjax()) {
    // X-Requested-With: XMLHttpRequest
}
```

---

## Accessing Files

Uploaded files are available through `getFile()` or `getFiles()`:

```php
$file = $req->getFile('avatar');

if ($file && $file['error'] === UPLOAD_ERR_OK) {
    move_uploaded_file($file['tmp_name'], 'uploads/' . $file['name']);
}
```

For multiple uploads under the same key:

```php
$file1 = $req->getFile('images', 0);
$file2 = $req->getFile('images', 1);
```

---

## Getting Client Info

```php
$ip = $req->getIp();
$ua = $req->getUserAgent();
```

The IP detection respects proxy headers like `X-Forwarded-For`,
but always falls back to `REMOTE_ADDR`.

---

## Handling Tokens or Auth Checks

Because the `Request` gives you direct access to headers and params,
you can implement lightweight auth guards easily:

```php
if ($req->getQueryParam('token') !== 'secret') {
    $res->withText('Unauthorized')->setStatusCode(401)->send();
    return;
}
```

!!! tip "Combine with middleware"
	For real-world applications, prefer wrapping authentication or validation logic in a middleware — it’s cleaner and reusable across multiple routes.

---

## Low-level Access

When needed, you can also inspect the raw body or decoded JSON directly:

```php
$raw = $req->getRawBody();
$json = $req->getJson(true); // associative array
```

---

## Summary

| Method                             | Description              |
| ---------------------------------- | ------------------------ |
| `getQueryParam($key, $default)`    | Read query string values |
| `getFormDataParam($key, $default)` | Read POST form fields    |
| `getJson()`                        | Decode JSON payloads     |
| `getHeader($name)`                 | Retrieve a header        |
| `getFile($key)`                    | Get uploaded file info   |
| `getIp()` / `getUserAgent()`       | Access client metadata   |
| `isAjax()` / `isSecure()`          | Detect request type      |

---

!!! note "Under the hood"
	Request sanitizes all query and form values automatically with `htmlspecialchars()` to prevent accidental HTML injection.
