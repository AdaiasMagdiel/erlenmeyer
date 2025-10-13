<?php

namespace AdaiasMagdiel\Erlenmeyer;

use InvalidArgumentException;
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
     * @var array HTTP headers.
     */
    private array $headers = [];

    /**
     * @var string|null Response body content.
     */
    private ?string $body = null;

    /**
     * @var bool Indicates whether the response has been sent.
     */
    private bool $isSent = false;

    /**
     * @var string Default content type.
     */
    private string $contentType = 'text/html';

    /**
     * Allows overriding native PHP functions (e.g., header()) for testing purposes.
     *
     * @var array<string, string>
     */
    private static array $functions = ["header" => 'header'];

    /**
     * Creates a new Response instance.
     *
     * @param int $statusCode Initial HTTP status code (default: 200).
     * @param array $headers Initial headers (optional).
     */
    public function __construct(int $statusCode = 200, array $headers = [])
    {
        $this->setStatusCode($statusCode);
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    /**
     * Updates internal callable function mappings.
     *
     * @param array $functions Associative array of callable replacements.
     */
    public static function updateFunctions(array $functions = []): void
    {
        self::$functions = array_merge(self::$functions, $functions);
    }

    /**
     * Sets the HTTP status code.
     *
     * @param int $code The status code (100–599).
     * @return self
     * @throws InvalidArgumentException If the code is out of range.
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
     * Returns the HTTP status code.
     *
     * @return int Current status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Sets an HTTP header.
     *
     * @param string $name Header name.
     * @param string $value Header value.
     * @return self
     * @throws RuntimeException If headers have already been sent.
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
     * Removes a previously set header.
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
     * Returns all headers.
     *
     * @return array All headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Sets the response content type.
     *
     * @param string $contentType MIME type (e.g., text/html, application/json).
     * @return self
     */
    public function setContentType(string $contentType): self
    {
        $this->contentType = trim($contentType);
        return $this->setHeader('Content-Type', $contentType);
    }

    /**
     * Returns the current content type.
     *
     * @return string MIME type.
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Sets the response body.
     *
     * @param string $body Response content.
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
     * @return string|null Response body content or null if unset.
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Sets an HTML response body.
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
     * Renders HTML content from a template file.
     *
     * @param string $templatePath Path to the template file.
     * @param array $data Data variables passed to the template.
     * @return self
     * @throws RuntimeException If the template cannot be found.
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
     * Sets a JSON response body.
     *
     * @param mixed $data Data to encode as JSON.
     * @param int $options Encoding options for json_encode (default: JSON_PRETTY_PRINT).
     * @return self
     * @throws RuntimeException If encoding fails.
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
     * @param string $text Text content.
     * @return self
     */
    public function withText(string $text): self
    {
        $this->setContentType('text/plain; charset=UTF-8');
        $this->setBody($text);
        return $this;
    }

    /**
     * Performs an HTTP redirect.
     *
     * @param string $url Destination URL.
     * @param int $statusCode Redirect status code (default: 302).
     * @return self
     * @throws InvalidArgumentException If the status code is not a valid redirect code.
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
     * @param int $expire Expiration timestamp (0 for session cookie).
     * @param string $path Cookie path.
     * @param string $domain Cookie domain.
     * @param bool $secure If true, send only over HTTPS.
     * @param bool $httpOnly If true, restrict cookie from JavaScript access.
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
     * Sends the response to the client.
     *
     * @throws RuntimeException If the response has already been sent or headers cannot be sent.
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
     * Returns whether the response has already been sent.
     *
     * @return bool True if sent, false otherwise.
     */
    public function isSent(): bool
    {
        return $this->isSent;
    }

    /**
     * Clears headers and body but retains the current status code.
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
     * Sets an error response with an optional message and logger callback.
     *
     * @param int $statusCode HTTP status code.
     * @param string $message Optional error message.
     * @param callable|null $logger Optional callback for logging (receives code and message).
     * @return self
     */
    public function withError(int $statusCode, string $message = '', ?callable $logger = null): self
    {
        $this->setStatusCode($statusCode);
        if ($message) {
            $this->withText($message);
        }
        if ($logger) {
            $logger($statusCode, $message);
        }
        return $this;
    }

    /**
     * Sends a file as a downloadable attachment.
     *
     * @param string $filePath Absolute file path.
     * @return self
     * @throws RuntimeException If the file is not readable.
     */
    public function withFile(string $filePath): self
    {
        if (!is_readable($filePath)) {
            throw new RuntimeException("File not readable: $filePath");
        }
        $this->setHeader('Content-Disposition', 'attachment; filename="' . basename($filePath) . '"');
        $this->setContentType(Assets::detectMimeType($filePath));
        $this->setBody(file_get_contents($filePath));
        return $this;
    }

    /**
     * Configures Cross-Origin Resource Sharing (CORS) headers.
     *
     * @param array $options Supported keys:
     *  - origin: Allowed origin(s)
     *  - methods: Allowed HTTP methods
     *  - headers: Allowed request headers
     *  - credentials: Boolean, allow credentials
     *  - max_age: Cache duration in seconds
     * @return self
     * @throws RuntimeException If called after the response has been sent.
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
}
