<?php

namespace AdaiasMagdiel\Erlenmeyer;

use RuntimeException;

/**
 * Class to encapsulate and process HTTP request data.
 */
class Request
{
    /**
     * @var array Request headers.
     */
    private $headers;

    /**
     * @var string HTTP request method (GET, POST, etc.).
     */
    private $method;

    /**
     * @var string Request URI without query string.
     */
    private $uri;

    /**
     * @var array Query string parameters.
     */
    private $queryParams;

    /**
     * @var array Form data (POST).
     */
    private $formData;

    /**
     * @var array|null Decoded JSON body data.
     */
    private $jsonData;

    /**
     * @var string|null JSON decoding error, if any.
     */
    private $jsonError;

    /**
     * @var string|null Raw request body.
     */
    private $rawBody;

    /**
     * @var array Uploaded files.
     */
    private $files;

    /**
     * @var string|null Client IP address.
     */
    private $ip;

    /**
     * @var string|null Client User-Agent.
     */
    private $userAgent;

    /**
     * @var array $_SERVER data (for test injection).
     */
    private $server;

    /**
     * Constructor for the Request class.
     *
     * @param array|null $server $_SERVER data (for tests or CLI).
     * @param array|null $get $_GET data.
     * @param array|null $post $_POST data.
     * @param array|null $files $_FILES data.
     * @param string $inputStream Input stream (default: php://input).
     */
    public function __construct(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null,
        ?array $files = null,
        string $inputStream = 'php://input'
    ) {
        $this->server = $server ?? $_SERVER;
        $this->initHeaders();
        $this->initMethod($post ?? $_POST);
        $this->initUri();
        $this->initQueryParams($get ?? $_GET);
        $this->initFormData($post ?? $_POST);
        $this->initRawBody($inputStream);
        $this->initFiles($files ?? $_FILES);
        $this->initClientInfo();
    }

    /**
     * Initializes request headers.
     */
    private function initHeaders(): void
    {
        if (function_exists('getallheaders')) {
            $this->headers = getallheaders() ?: [];
        } else {
            $this->headers = [];
            foreach ($this->server as $name => $value) {
                if (strpos($name, 'HTTP_') === 0) {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    // Light sanitization: trim and ensure string
                    $this->headers[$headerName] = trim(filter_var($value, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE) ?? '');
                }
            }
        }
    }

    /**
     * Determines the HTTP request method.
     *
     * @param array $post $_POST data to check for method override.
     */
    private function initMethod(array $post): void
    {
        $this->method = filter_var($this->server['REQUEST_METHOD'] ?? 'GET', FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE) ?? 'GET';
        if ($this->method === 'POST') {
            if (isset($post['_method'])) {
                // Sanitization of method override
                $this->method = strtoupper(trim(filter_var($post['_method'], FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE) ?? ''));
            } elseif ($override = filter_input(INPUT_SERVER, 'HTTP_X_HTTP_METHOD_OVERRIDE', FILTER_UNSAFE_RAW, ['options' => ['default' => null]])) {
                $this->method = strtoupper(trim($override));
            }
        }
    }

    /**
     * Captures the request URI, removing the query string.
     */
    private function initUri(): void
    {
        $this->uri = filter_var($this->server['REQUEST_URI'] ?? '/', FILTER_SANITIZE_URL) ?? '/';
        if (($pos = strpos($this->uri, '?')) !== false) {
            $this->uri = substr($this->uri, 0, $pos);
        }
    }

    /**
     * Initializes query string parameters.
     *
     * @param array $get $_GET data.
     */
    private function initQueryParams(array $get): void
    {
        // Escapes special characters to prevent XSS when displaying
        $this->queryParams = array_map(function ($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }, $get);
    }

    /**
     * Initializes form data (POST).
     *
     * @param array $post $_POST data.
     */
    private function initFormData(array $post): void
    {
        // Escapes special characters to prevent XSS when displaying
        $this->formData = array_map(function ($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }, $post);
    }

    /**
     * Captures the raw request body.
     *
     * @param string $inputStream Input stream.
     */
    private function initRawBody(string $inputStream): void
    {
        $this->rawBody = @file_get_contents($inputStream) ?: null;
    }

    /**
     * Initializes JSON body data (lazy loading).
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
     * @param array $files $_FILES data.
     */
    private function initFiles(array $files): void
    {
        $this->files = $files;
    }

    /**
     * Initializes client information (IP and User-Agent).
     */
    private function initClientInfo(): void
    {
        $this->ip = filter_var($this->server['REMOTE_ADDR'] ?? null, FILTER_VALIDATE_IP);
        if ($forwarded = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_UNSAFE_RAW)) {
            $ips = explode(',', $forwarded);
            $this->ip = filter_var(trim($ips[0]), FILTER_VALIDATE_IP) ?: $this->ip;
        }
        // Light sanitization for User-Agent
        $this->userAgent = trim(filter_input(INPUT_SERVER, 'HTTP_USER_AGENT', FILTER_UNSAFE_RAW, ['options' => ['default' => null]]));
    }

    /**
     * Retrieves the value of a specific header.
     *
     * @param string $name Header name.
     * @return string|null Header value or null if not found.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Retrieves all request headers.
     *
     * @return array All headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Checks if a header exists.
     *
     * @param string $name Header name.
     * @return bool True if the header exists, false otherwise.
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    /**
     * Retrieves the HTTP request method.
     *
     * @return string HTTP method (GET, POST, etc.).
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Retrieves the request URI.
     *
     * @return string URI without query string.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Retrieves all query string parameters.
     *
     * @return array Query string parameters.
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * Retrieves a specific query string parameter.
     *
     * @param string $key Parameter key.
     * @param mixed $default Default value if the parameter does not exist.
     * @return mixed Parameter value or default value.
     */
    public function getQueryParam(string $key, $default = null)
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Retrieves all form data (POST).
     *
     * @return array Form data.
     */
    public function getFormData(): array
    {
        return $this->formData;
    }

    /**
     * Retrieves a specific form data parameter (POST).
     *
     * @param string $key Data key.
     * @param mixed $default Default value if the data does not exist.
     * @return mixed Data value or default value.
     */
    public function getFormDataParam(string $key, $default = null)
    {
        return $this->formData[$key] ?? $default;
    }

    /**
     * Retrieves JSON data from the request body.
     *
     * @param bool $assoc If true, returns an associative array; if false, returns an object.
     * @return mixed JSON data.
     * @throws RuntimeException If JSON decoding fails.
     */
    public function getJson(bool $assoc = true)
    {
        $this->initJson();
        if ($this->jsonError) {
            throw new RuntimeException("Failed to decode JSON: {$this->jsonError}");
        }
        return $assoc ? $this->jsonData : ($this->rawBody ? json_decode($this->rawBody) : null);
    }

    /**
     * Retrieves the JSON decoding error, if any.
     *
     * @return string|null Error message or null if no error occurred.
     */
    public function getJsonError(): ?string
    {
        $this->initJson();
        return $this->jsonError;
    }

    /**
     * Retrieves the raw request body.
     *
     * @return string|null Raw body or null if not available.
     */
    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }

    /**
     * Retrieves all uploaded files.
     *
     * @return array Uploaded files.
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Retrieves a specific uploaded file.
     *
     * @param string $key File key.
     * @param int|null $index Index for multiple files.
     * @return array|null File data or null if not found.
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
     * Retrieves the client IP address.
     *
     * @return string|null IP address or null if not available.
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * Retrieves the client User-Agent.
     *
     * @return string|null User-Agent or null if not available.
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * Checks if the request is an AJAX request.
     *
     * @return bool True if it is an AJAX request, false otherwise.
     */
    public function isAjax(): bool
    {
        return isset($this->server['HTTP_X_REQUESTED_WITH']) &&
            strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Checks if the request uses HTTPS.
     *
     * @return bool True if HTTPS is used, false otherwise.
     */
    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ||
            ($this->server['SERVER_PORT'] ?? null) === 443;
    }
}
