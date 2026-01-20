<?php

namespace AdaiasMagdiel\Erlenmeyer\Testing;

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

/**
 * A lightweight testing client for simulating HTTP requests within the Erlenmeyer framework.
 *
 * This client allows integration testing by emulating HTTP requests to the App
 * without requiring a real web server. It ensures headers do not interfere with
 * CLI output and properly handles global state isolation.
 */
class ErlenClient
{
    /**
     * @var App The application instance under test.
     */
    private App $app;

    /**
     * @var array Default headers applied to every request.
     */
    private array $defaultHeaders = [];

    /**
     * @var bool Flag to enable or disable output display in routes.
     */
    private bool $showOutput = false;

    /**
     * Creates a new ErlenClient instance.
     *
     * @param App $app The application instance to handle requests.
     * @param bool $showOutput Whether to echo the response body to the terminal.
     */
    public function __construct(App $app, bool $showOutput = false)
    {
        $this->app = $app;
        $this->showOutput = $showOutput;

        Response::updateFunctions([
            'header' => function (string $header, bool $replace = true, int $response_code = 0) {}
        ]);
    }

    /**
     * Enable or disable output display for routes.
     *
     * @param bool $show The flag value.
     * @return self
     */
    public function showOutput(bool $show = true): self
    {
        $this->showOutput = $show;
        return $this;
    }

    /**
     * Defines default headers to be included in all subsequent requests.
     *
     * @param array $headers Associative array of headers.
     * @return self
     */
    public function withHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * Clears all default headers.
     *
     * @return self
     */
    public function resetHeaders(): self
    {
        $this->defaultHeaders = [];
        return $this;
    }

    // ------------------------------------------------------------
    // HTTP method shortcuts
    // ------------------------------------------------------------

    public function get(string $uri, array $options = []): Response
    {
        return $this->request('GET', $this->normalizeUri($uri), $options);
    }

    public function post(string $uri, array $options = []): Response
    {
        return $this->request('POST', $this->normalizeUri($uri), $options);
    }

    public function put(string $uri, array $options = []): Response
    {
        return $this->request('PUT', $this->normalizeUri($uri), $options);
    }

    public function patch(string $uri, array $options = []): Response
    {
        return $this->request('PATCH', $this->normalizeUri($uri), $options);
    }

    public function delete(string $uri, array $options = []): Response
    {
        return $this->request('DELETE', $this->normalizeUri($uri), $options);
    }

    public function head(string $uri, array $options = []): Response
    {
        return $this->request('HEAD', $this->normalizeUri($uri), $options);
    }

    public function options(string $uri, array $options = []): Response
    {
        return $this->request('OPTIONS', $this->normalizeUri($uri), $options);
    }

    // ------------------------------------------------------------
    // Core request executor
    // ------------------------------------------------------------

    /**
     * Executes an HTTP request against the application.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $uri The request URI.
     * @param array $options Additional request options.
     * @return Response The application response.
     */
    public function request(string $method, string $uri, array $options = []): Response
    {
        $method = strtoupper($method);
        $uri = $this->normalizeUri($uri);

        // Parse the URI into path and query components
        $parsedUri = parse_url($uri);
        $path = $parsedUri['path'] ?? '/';
        $query = $parsedUri['query'] ?? '';

        // Build a $_SERVER-like array
        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'PATH_INFO' => $path,
            'QUERY_STRING' => $query,
            'SCRIPT_NAME' => 'index.php', // Standardize script name
            'PHP_SELF' => '/index.php' . $path,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTP_HOST' => 'localhost',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REMOTE_ADDR' => '127.0.0.1', // Ensure IP is present
        ];

        // Merge headers with defaults and convert to $_SERVER format
        $headers = array_merge($this->defaultHeaders, $options['headers'] ?? []);
        foreach ($headers as $name => $value) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $server[$serverKey] = $value;
        }

        // Determine body and content type
        $json = $options['json'] ?? null;
        $form = $options['form_params'] ?? [];
        $files = $options['files'] ?? [];
        $body = $options['body'] ?? null;

        if (isset($headers['Content-Type'])) {
            $server['CONTENT_TYPE'] = $headers['Content-Type'];
        } elseif ($json !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
        } elseif (!empty($form)) {
            $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        }

        if ($json !== null) {
            $body = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } elseif (!empty($form)) {
            $body = http_build_query($form);
        }

        // Add query parameters if provided
        $queryParams = $options['query'] ?? [];
        if (!empty($queryParams)) {
            $queryString = http_build_query($queryParams);
            // Append properly handling existing query params
            $separator = (str_contains($uri, '?')) ? '&' : '?';
            $uri .= $separator . $queryString;

            // Update SERVER vars
            $server['REQUEST_URI'] = $uri;
            // Append to existing query string if present
            $server['QUERY_STRING'] = ($server['QUERY_STRING'] ? $server['QUERY_STRING'] . '&' : '') . $queryString;
        }

        // Create Request instance
        // Note: We intentionally pass 'queryParams' even though Request might parse 
        // QUERY_STRING, to ensure maximum compatibility.
        $request = new Request(
            $server,
            $queryParams,
            $form,
            $files,
            'php://memory',
            $body
        );

        $response = new Response();

        // Capture output buffer to simulate network transmission and suppress noise
        if (!$this->showOutput) {
            ob_start();
        }

        try {
            // Process the request through the App
            $result = $this->app->handle($request, $response);

            // Trigger the "send" logic to ensure body is rendered and headers are processed
            // (Even though our mocked header function prevents actual CLI output)
            if (!$result->isSent()) {
                $result->send();
            }

            return $result;
        } finally {
            // Clean the buffer. We discard the output unless showOutput is true,
            // because the test should assert against the $result object, not stdout.
            if (!$this->showOutput) {
                ob_end_clean();
            }
        }
    }

    /**
     * Normalizes a URI in the same way as the App class.
     *
     * @param string $uri The URI to normalize.
     * @return string Normalized URI.
     */
    private function normalizeUri(string $uri): string
    {
        $parsed = parse_url($uri);
        $path = $parsed['path'] ?? '/';

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $normalizedUri = $path;

        if (!empty($parsed['query'])) {
            $normalizedUri .= '?' . $parsed['query'];
        }

        if (!empty($parsed['fragment'])) {
            $normalizedUri .= '#' . $parsed['fragment'];
        }

        return $normalizedUri;
    }
}
