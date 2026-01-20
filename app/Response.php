<?php

namespace AdaiasMagdiel\Erlenmeyer;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Handles HTTP responses, including headers, status codes, and body rendering.
 */
class Response
{
    /**
     * @var int HTTP status code.
     */
    private int $statusCode = 200;

    /**
     * @var array<string, string> HTTP headers as key-value pairs.
     */
    private array $headers = [];

    /**
     * @var string|null Response body content.
     */
    private ?string $body = null;

    /**
     * @var bool Indicates whether the response has been sent to the client.
     */
    private bool $isSent = false;

    /**
     * @var string The default content type for the response.
     */
    private string $contentType = 'text/html';

    /**
     * Allows overriding native PHP functions for testing purposes (e.g., mocking header()).
     *
     * @var array<string, callable>
     */
    private static array $functions = ["header" => 'header'];

    /**
     * Creates a new Response instance.
     *
     * @param int $statusCode Initial HTTP status code (default: 200).
     * @param array<string, string> $headers Initial headers as an associative array.
     */
    public function __construct(int $statusCode = 200, array $headers = [])
    {
        $this->setStatusCode($statusCode);
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    /**
     * Updates internal callable function mappings for testing/mocking.
     *
     * @param array<string, callable> $functions Associative array of function names and their callables.
     */
    public static function updateFunctions(array $functions = []): void
    {
        self::$functions = array_merge(self::$functions, $functions);
    }

    /**
     * Sets the HTTP status code.
     *
     * @param int $code The status code (must be between 100 and 599).
     * @return self
     * @throws InvalidArgumentException If the code is out of the valid HTTP range.
     */
    public function setStatusCode(int $code): self
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException("Invalid HTTP status code: $code");
        }
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Returns the current HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Decodes the response body as JSON.
     *
     * @return array<mixed>|null Associative array of decoded data, or null if decoding fails.
     */
    public function getJson(): array|null
    {
        try {
            $raw = $this->getBody();
            if (is_null($raw)) return null;

            return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Sets an HTTP header.
     *
     * @param string $name Header name (e.g., "X-Custom-Header").
     * @param string $value Header value.
     * @return self
     * @throws RuntimeException If headers have already been sent to the client.
     */
    public function setHeader(string $name, string $value): self
    {
        if ($this->isSent) {
            throw new RuntimeException("Cannot set headers after the response has been sent.");
        }
        $this->headers[$name] = trim($value);
        return $this;
    }

    /**
     * Removes a previously set header by name.
     *
     * @param string $name Header name.
     * @return self
     * @throws RuntimeException If headers have already been sent.
     */
    public function removeHeader(string $name): self
    {
        if ($this->isSent) {
            throw new RuntimeException("Cannot remove headers after the response has been sent.");
        }
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * Returns all currently set headers.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Sets the response Content-Type header.
     *
     * @param string $contentType MIME type (e.g., "application/json").
     * @return self
     */
    public function setContentType(string $contentType): self
    {
        $this->contentType = trim($contentType);
        return $this->setHeader('Content-Type', $contentType);
    }

    /**
     * Gets the current Content-Type value.
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Sets the raw response body.
     *
     * @param string $body Content to be sent.
     * @return self
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Returns the response body.
     *
     * @return string|null Raw body content or null if not set.
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Prepares an HTML response with the appropriate Content-Type.
     *
     * @param string $html HTML content.
     * @return self
     */
    public function withHtml(string $html): self
    {
        $this->setContentType('text/html; charset=UTF-8');
        $this->setBody($html);
        return $this;
    }

    /**
     * Renders a template from PHP file and sets it as the response body.
     *
     * @param string $templatePath Absolute path to the template file.
     * @param array<string, mixed> $data Associative array of variables to extract into the template scope.
     * @return self
     * @throws RuntimeException If the template file does not exist.
     */
    public function withTemplate(string $templatePath, array $data = []): self
    {
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template not found: $templatePath");
        }

        ob_start();
        extract($data, EXTR_SKIP);
        include $templatePath;
        $html = ob_get_clean();

        return $this->withHtml($html);
    }

    /**
     * Sets a JSON-encoded response body.
     *
     * @param mixed $data Data to be encoded.
     * @param int $options Bitmask of JSON encoding options (default: JSON_PRETTY_PRINT).
     * @return self
     * @throws RuntimeException If JSON encoding fails.
     */
    public function withJson($data, int $options = JSON_PRETTY_PRINT): self
    {
        $json = json_encode($data, $options);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to serialize JSON: " . json_last_error_msg());
        }

        $this->setContentType('application/json; charset=UTF-8');
        $this->setBody($json);
        return $this;
    }

    /**
     * Sets a plain text response body.
     *
     * @param string $text Plain text content.
     * @return self
     */
    public function withText(string $text): self
    {
        $this->setContentType('text/plain; charset=UTF-8');
        $this->setBody($text);
        return $this;
    }

    /**
     * Sets up an HTTP redirect.
     *
     * @param string $url Target URL to redirect to.
     * @param int $statusCode Redirect status code (typically 301 or 302).
     * @return self
     * @throws InvalidArgumentException If the status code is not in the 3xx range.
     */
    public function redirect(string $url, int $statusCode = 302): self
    {
        if ($statusCode < 300 || $statusCode > 399) {
            throw new InvalidArgumentException("Invalid status code for redirection: $statusCode");
        }

        $this->setStatusCode($statusCode);
        $this->setHeader('Location', filter_var($url, FILTER_SANITIZE_URL));
        $this->setBody('');
        return $this;
    }

    /**
     * Adds a Set-Cookie header to the response.
     *
     * @param string $name Cookie name.
     * @param string $value Cookie value.
     * @param int $expire Expiration time as a Unix timestamp (default: 0 for session).
     * @param string $path Scope path (default: "/").
     * @param string $domain Cookie domain scope.
     * @param bool $secure Whether the cookie should only be transmitted over HTTPS.
     * @param bool $httpOnly Whether the cookie should be inaccessible to client-side scripts.
     * @return self
     */
    public function withCookie(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true
    ): self {
        $cookie = urlencode($name) . '=' . urlencode($value);
        if ($expire) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s T', $expire);
        }
        if ($path) {
            $cookie .= '; Path=' . $path;
        }
        if ($domain) {
            $cookie .= '; Domain=' . $domain;
        }
        if ($secure) {
            $cookie .= '; Secure';
        }
        if ($httpOnly) {
            $cookie .= '; HttpOnly';
        }

        return $this->setHeader('Set-Cookie', $cookie);
    }

    /**
     * Sends headers and the body to the client.
     *
     * @throws RuntimeException If response was already sent or headers are already emitted by PHP.
     */
    public function send(): void
    {
        if ($this->isSent) {
            throw new RuntimeException("The response has already been sent.");
        }

        if (headers_sent()) {
            throw new RuntimeException("Headers have already been sent, cannot send response.");
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            self::$functions['header']("$name: $value", true);
        }

        if ($this->body !== null) {
            echo $this->body;
        }

        $this->isSent = true;
    }

    /**
     * Checks if the response has already been sent.
     *
     * @return bool
     */
    public function isSent(): bool
    {
        return $this->isSent;
    }

    /**
     * Resets headers and body to default values, preserving the status code.
     *
     * @return self
     * @throws RuntimeException If the response has already been sent.
     */
    public function clear(): self
    {
        if ($this->isSent) {
            throw new RuntimeException("Cannot clear the response after it has been sent.");
        }

        $this->headers = [];
        $this->body = null;
        $this->contentType = 'text/html';
        return $this;
    }

    /**
     * Sets an error response with a custom message and optional logging.
     *
     * @param int $statusCode HTTP error status code.
     * @param string $message Error message.
     * @param bool $json Whether to return the error as JSON (default: true).
     * @param callable|null $logger Callback for logging (params: int $code, string $message).
     * @return self
     */
    public function withError(int $statusCode, string $message = '', bool $json = true, ?callable $logger = null): self
    {
        $this->setStatusCode($statusCode);
        if ($message) {
            if ($json) {
                $this->withJson(['error' => $message]);
            } else {
                $this->withText($message);
            }
        }
        if ($logger) {
            $logger($statusCode, $message);
        }
        return $this;
    }

    /**
     * Prepares a file download response.
     *
     * @param string $filePath Full path to the file.
     * @return self
     * @throws RuntimeException If the file is not found or not readable.
     */
    public function withFile(string $filePath): self
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException("File not readable: $filePath");
        }
        $this->setHeader('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"');
        $this->setContentType($this->detectMimeType($filePath));
        $this->setBody(file_get_contents($filePath));
        return $this;
    }

    /**
     * Configures CORS (Cross-Origin Resource Sharing) headers.
     *
     * @param array{
     * origin?: string|string[],
     * methods?: string|string[],
     * headers?: string|string[],
     * credentials?: bool,
     * max_age?: int
     * } $options CORS configuration options.
     * @return self
     * @throws RuntimeException If headers have already been sent.
     */
    public function setCORS(array $options): self
    {
        if ($this->isSent) {
            throw new RuntimeException("Cannot configure CORS after the response has been sent.");
        }

        if (isset($options['origin'])) {
            $origin = is_array($options['origin']) ? implode(', ', $options['origin']) : $options['origin'];
            $this->setHeader('Access-Control-Allow-Origin', $origin);
        }

        if (isset($options['methods'])) {
            $methods = is_array($options['methods']) ? implode(', ', $options['methods']) : $options['methods'];
            $this->setHeader('Access-Control-Allow-Methods', $methods);
        }

        if (isset($options['headers'])) {
            $headers = is_array($options['headers']) ? implode(', ', $options['headers']) : $options['headers'];
            $this->setHeader('Access-Control-Allow-Headers', $headers);
        }

        if (!empty($options['credentials'])) {
            $this->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        if (isset($options['max_age'])) {
            $this->setHeader('Access-Control-Max-Age', (string) $options['max_age']);
        }

        return $this;
    }

    /**
     * Detects the MIME type of a file.
     *
     * @param string $filePath Path to the file.
     * @return string Detected MIME type.
     */
    private function detectMimeType(string $filePath): string
    {
        // 1. Try native PHP detection
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($filePath);
            if ($mime) {
                return $mime;
            }
        }

        // 2. Simple fallback for common types
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return match ($extension) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'txt' => 'text/plain',
            'html', 'htm' => 'text/html',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
