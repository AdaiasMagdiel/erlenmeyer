<?php

namespace AdaiasMagdiel\Erlenmeyer;

use RuntimeException;
use stdClass;

/**
 * Represents an HTTP request and provides access to its data.
 */
class Request
{
    /**
     * @var array Request headers.
     */
    private array $headers;

    /**
     * @var string HTTP request method (GET, POST, etc.).
     */
    private string $method;

    /**
     * @var string Request URI without query string.
     */
    private string $uri;

    /**
     * @var array Query string parameters.
     */
    private array $queryParams;

    /**
     * @var array Form data (POST).
     */
    private array $formData;

    /**
     * @var array|null Decoded JSON body data.
     */
    private ?array $jsonData = null;

    /**
     * @var string|null JSON decoding error, if any.
     */
    private ?string $jsonError = null;

    /**
     * @var string|null Raw request body.
     */
    private ?string $rawBody = null;

    /**
     * @var array Uploaded files.
     */
    private array $files = [];

    /**
     * @var string|null Client IP address.
     */
    private ?string $ip = null;

    /**
     * @var string|null Client User-Agent.
     */
    private ?string $userAgent = null;

    /**
     * @var array Server data (used for dependency injection in tests).
     */
    private array $server;

    /**
     * Creates a new Request instance.
     *
     * @param array|null $server The server data (typically $_SERVER).
     * @param array|null $get The query string parameters ($_GET).
     * @param array|null $post The POST form data ($_POST).
     * @param array|null $files The uploaded files ($_FILES).
     * @param string $inputStream Input stream for reading the raw body. Default: 'php://input'.
     * @param string|null $rawBody Optional raw body data.
     */
    public function __construct(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null,
        ?array $files = null,
        string $inputStream = 'php://input',
        ?string $rawBody = null
    ) {
        $this->server = $server ?? $_SERVER;
        $this->initHeaders();
        $this->initMethod($post ?? $_POST);
        $this->initUri();
        $this->initQueryParams($get ?? $_GET);
        $this->initFormData($post ?? $_POST);
        $this->initFiles($files ?? $_FILES);
        $this->initClientInfo();

        if ($rawBody !== null) {
            $this->rawBody = $rawBody;
        } else {
            $this->initRawBody($inputStream);
        }
    }

    /**
     * Initializes request headers from the server array.
     */
    private function initHeaders(): void
    {
        $this->headers = [];

        foreach ($this->server as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $this->headers[$headerName] = trim((string) $value);
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headerName = str_replace('_', '-', $name);
                $this->headers[$headerName] = trim((string) $value);
            }
        }
    }

    /**
     * Determines the HTTP request method.
     *
     * @param array $post POST data used to check for method override.
     */
    private function initMethod(array $post): void
    {
        $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($this->method === 'POST') {
            if (isset($post['_method'])) {
                $this->method = strtoupper(trim((string) $post['_method']));
            } elseif (isset($this->server['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $this->method = strtoupper(trim((string) $this->server['HTTP_X_HTTP_METHOD_OVERRIDE']));
            }
        }
    }

    /**
     * Initializes the request URI, removing any query string.
     */
    private function initUri(): void
    {
        $this->uri = filter_var($this->server['REQUEST_URI'] ?? '/', FILTER_SANITIZE_URL) ?? '/';
        if (($pos = strpos($this->uri, '?')) !== false) {
            $this->uri = substr($this->uri, 0, $pos);
        }
        if ($this->uri === '') {
            $this->uri = '/';
        }
    }

    /**
     * Initializes query string parameters.
     *
     * @param array $get Query string parameters ($_GET).
     */
    private function initQueryParams(array $get): void
    {
        $this->queryParams = array_map(fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), $get);
    }

    /**
     * Initializes POST form data.
     *
     * @param array $post POST data ($_POST).
     */
    private function initFormData(array $post): void
    {
        $this->formData = array_map(fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), $post);
    }

    /**
     * Reads the raw request body.
     *
     * @param string $inputStream The input stream path.
     */
    private function initRawBody(string $inputStream): void
    {
        $this->rawBody = @file_get_contents($inputStream) ?: null;
    }

    /**
     * Lazily initializes JSON data from the request body.
     */
    private function initJson(): void
    {
        if ($this->jsonData !== null || $this->jsonError !== null) {
            return;
        }

        $this->jsonData = null;
        $this->jsonError = null;

        $contentType = $this->getHeader('Content-Type') ?? '';
        if (stripos($contentType, 'application/json') === 0 && $this->rawBody !== null) {
            $this->jsonData = json_decode($this->rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->jsonError = json_last_error_msg();
                $this->jsonData = null;
            }
        }
    }

    /**
     * Initializes uploaded files.
     *
     * @param array $files Uploaded files ($_FILES).
     */
    private function initFiles(array $files): void
    {
        $this->files = $files;
    }

    /**
     * Initializes client metadata (IP and User-Agent).
     */
    private function initClientInfo(): void
    {
        $this->ip = filter_var($this->server['REMOTE_ADDR'] ?? null, FILTER_VALIDATE_IP);

        if (isset($this->server['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = $this->server['HTTP_X_FORWARDED_FOR'];
            $ips = explode(',', $forwarded);
            $this->ip = filter_var(trim($ips[0]), FILTER_VALIDATE_IP) ?: $this->ip;
        }

        $this->userAgent = $this->server['HTTP_USER_AGENT'] ?? null;
        if ($this->userAgent !== null) {
            $this->userAgent = trim($this->userAgent);
        }
    }

    /**
     * Returns a header value by name.
     *
     * @param string $name The header name.
     * @return string|null The header value or null if not found.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Returns all headers.
     *
     * @return array All request headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Checks if a specific header exists.
     *
     * @param string $name Header name.
     * @return bool True if the header exists, false otherwise.
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Returns the HTTP method (GET, POST, etc.).
     *
     * @return string HTTP method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Returns the request URI (without query string).
     *
     * @return string The request URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Returns all query parameters.
     *
     * @return array Query parameters.
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Returns a specific query parameter.
     *
     * @param string $key The parameter key.
     * @param mixed $default Default value if not found.
     * @return mixed The parameter value or the default.
     */
    public function getQueryParam(string $key, $default = null)
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Returns all POST form data.
     *
     * @return array Form data.
     */
    public function getFormData(): array
    {
        return $this->formData;
    }

    /**
     * Returns a specific POST form value.
     *
     * @param string $key The key name.
     * @param mixed $default Default value if not found.
     * @return mixed The value or default.
     */
    public function getFormDataParam(string $key, $default = null): mixed
    {
        return $this->formData[$key] ?? $default;
    }

    /**
     * Returns decoded JSON body data.
     *
     * @param bool $assoc Return associative array if true, object if false.
     * @param bool $ignoreContentType If true, ignore the Content-Type check.
     * @return mixed The decoded JSON data.
     * @throws RuntimeException If JSON decoding fails or Content-Type is invalid.
     */
    public function getJson(bool $assoc = true, bool $ignoreContentType = false)
    {
        $this->initJson();
        $contentType = $this->getHeader('Content-Type') ?? '';

        if (!$ignoreContentType) {
            if (stripos($contentType, 'application/json') !== 0) {
                throw new RuntimeException("Invalid Content-Type: expected application/json, got {$contentType}");
            }
            if ($this->jsonError) {
                throw new RuntimeException("Failed to decode JSON: {$this->jsonError}");
            }
            return $assoc ? $this->jsonData : ($this->rawBody ? json_decode($this->rawBody) : null);
        }

        if ($this->rawBody !== null) {
            $decoded = json_decode($this->rawBody, $assoc);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            return $decoded;
        }

        return null;
    }

    /**
     * Returns the JSON decoding error message, if any.
     *
     * @return string|null The error message or null if none.
     */
    public function getJsonError(): ?string
    {
        $this->initJson();
        return $this->jsonError;
    }

    /**
     * Returns the raw request body.
     *
     * @return string|null The raw request body.
     */
    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }

    /**
     * Returns all uploaded files.
     *
     * @return array The uploaded files.
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Returns a specific uploaded file by key (and optional index for multi-files).
     *
     * @param string $key File key.
     * @param int|null $index Optional index for multiple uploads.
     * @return array|null File information or null if not found.
     */
    public function getFile(string $key, ?int $index = null): ?array
    {
        if ($index !== null && isset($this->files[$key]['name'][$index])) {
            return [
                'name' => $this->files[$key]['name'][$index],
                'type' => $this->files[$key]['type'][$index],
                'tmp_name' => $this->files[$key]['tmp_name'][$index],
                'error' => $this->files[$key]['error'][$index],
                'size' => $this->files[$key]['size'][$index],
            ];
        }
        return $this->files[$key] ?? null;
    }

    /**
     * Returns the client IP address.
     *
     * @return string|null The IP address.
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * Returns the client User-Agent string.
     *
     * @return string|null The User-Agent.
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * Checks whether the request is an AJAX request.
     *
     * @return bool True if AJAX, false otherwise.
     */
    public function isAjax(): bool
    {
        return isset($this->server['HTTP_X_REQUESTED_WITH'])
            && strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Checks whether the request was made over HTTPS.
     *
     * @return bool True if secure, false otherwise.
     */
    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (($this->server['SERVER_PORT'] ?? null) == 443);
    }
}
