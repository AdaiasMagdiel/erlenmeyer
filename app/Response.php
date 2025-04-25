<?php

namespace AdaiasMagdiel\Erlenmeyer;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class for managing HTTP responses, including HTML, JSON, headers, and status codes.
 */
class Response
{
    /**
     * @var int HTTP status code.
     */
    private int $statusCode = 200;

    /**
     * @var array Headers to be sent.
     */
    private array $headers = [];

    /**
     * @var string|null Response content.
     */
    private ?string $body = null;

    /**
     * @var bool Indicates whether the response has been sent.
     */
    private bool $isSent = false;

    /**
     * @var string Default content type (Content-Type).
     */
    private string $contentType = 'text/html';

    /**
     * An associative array mapping function names to their callable implementations.
     * Used to allow overriding native PHP functions (e.g., 'header') for testing or dependency injection.
     *
     * @var array<string, string> 
     */
    private static array $functions = ["header" => 'header'];

    /**
     * Constructor for the Response class.
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

    public static function updateFunctions(array $functions = [])
    {
        self::$functions = array_merge(self::$functions, $functions);
    }

    /**
     * Sets the HTTP status code.
     *
     * @param int $code Status code (e.g., 200, 404, 500).
     * @return self For method chaining.
     * @throws InvalidArgumentException If the status code is invalid.
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
     * Retrieves the HTTP status code.
     *
     * @return int Status code.
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
     * @return self For method chaining.
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
     * Removes an HTTP header.
     *
     * @param string $name Header name.
     * @return self For method chaining.
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
     * Retrieves all defined headers.
     *
     * @return array Headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Sets the content type (Content-Type).
     *
     * @param string $contentType Content type (e.g., text/html, application/json).
     * @return self For method chaining.
     */
    public function setContentType(string $contentType): self
    {
        $this->contentType = trim($contentType);
        return $this->setHeader('Content-Type', $contentType);
    }

    /**
     * Retrieves the current content type.
     *
     * @return string Content type.
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Sets the response body.
     *
     * @param string $body Response content.
     * @return self For method chaining.
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Retrieves the response body.
     *
     * @return string|null Response body or null if not set.
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * Renders HTML directly.
     *
     * @param string $html HTML content.
     * @return self For method chaining.
     */
    public function withHtml(string $html): self
    {
        $this->setContentType('text/html; charset=UTF-8');
        $this->setBody($html);
        return $this;
    }

    /**
     * Renders HTML from a template file.
     *
     * @param string $templatePath Path to the template file.
     * @param array $data Data to be passed to the template.
     * @return self For method chaining.
     * @throws RuntimeException If the template is not found.
     */
    public function withTemplate(string $templatePath, array $data = []): self
    {
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template not found: $templatePath");
        }

        // Uses output buffering to capture template content
        ob_start();
        extract($data, EXTR_SKIP);
        include $templatePath;
        $html = ob_get_clean();

        return $this->withHtml($html);
    }

    /**
     * Sends a JSON response.
     *
     * @param mixed $data Data to be serialized as JSON.
     * @param int $options Options for json_encode (default: JSON_PRETTY_PRINT).
     * @return self For method chaining.
     * @throws RuntimeException If JSON serialization fails.
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
     * Sends a plain text response.
     *
     * @param string $text Text content.
     * @return self For method chaining.
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
     * @param string $url URL to redirect to.
     * @param int $statusCode Status code (default: 302).
     * @return self For method chaining.
     * @throws InvalidArgumentException If the status code is invalid for redirection.
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
     * Sets a cookie in the response.
     *
     * @param string $name Cookie name.
     * @param string $value Cookie value.
     * @param int $expire Expiration timestamp (default: 0 = session end).
     * @param string $path Cookie path (default: /).
     * @param string $domain Cookie domain (default: empty).
     * @param bool $secure HTTPS only (default: false).
     * @param bool $httpOnly Inaccessible via JavaScript (default: true).
     * @return self For method chaining.
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
     * @return void
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

        // Sends the status code
        http_response_code($this->statusCode);

        // Sends the headers
        foreach ($this->headers as $name => $value) {
            self::$functions['header']("$name: $value", true);
        }

        // Sends the body, if any
        if ($this->body !== null) {
            echo $this->body;
        }

        $this->isSent = true;
    }

    /**
     * Checks if the response has been sent.
     *
     * @return bool True if the response has been sent, false otherwise.
     */
    public function isSent(): bool
    {
        return $this->isSent;
    }

    /**
     * Clears the response body and headers, keeping the status code.
     *
     * @return self For method chaining.
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
     * Sets an error response with a status code and message.
     *
     * @param int $statusCode Status code (e.g., 404, 500).
     * @param string $message Error message (optional).
     * @param callable|null $logger An optional callback to log the error. It should accept two parameters: the status code and the message.
     * @return self For method chaining.
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
     * Sets the response to send a file as an attachment.
     *
     * This method checks if the specified file is readable. If it is, it sets the
     * 'Content-Disposition' header to prompt the client to download the file,
     * determines the MIME type using Assets::detectMimeType, and sets the response
     * body to the file's contents.
     *
     * @param string $filePath The path to the file to be sent.
     * @return self For method chaining.
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
     * Configures CORS headers based on the provided options.
     *
     * @param array $options CORS options:
     *   - 'origin' => Who can access (e.g., '*', 'https://my-site.com')
     *   - 'methods' => Allowed methods (e.g., 'GET,POST' or ['GET', 'POST'])
     *   - 'headers' => Allowed headers (e.g., 'Content-Type')
     *   - 'credentials' => If true, allows cookies/authentication
     *   - 'max_age' => Cache time in seconds (e.g., 86400 for 1 day)
     * @return self For method chaining.
     * @throws RuntimeException If CORS is configured after the response has been sent.
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

        if (isset($options['credentials']) && $options['credentials'] === true) {
            $this->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        if (isset($options['max_age'])) {
            $this->setHeader('Access-Control-Max-Age', (string)$options['max_age']);
        }

        return $this;
    }
}
