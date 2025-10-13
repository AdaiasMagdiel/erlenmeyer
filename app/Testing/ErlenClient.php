<?php

namespace AdaiasMagdiel\Erlenmeyer\Testing;

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

/**
 * A lightweight testing client for simulating HTTP requests within the Erlenmeyer framework.
 *
 * This client allows integration testing by emulating HTTP requests to the App
 * without requiring a real web server. It provides methods for all common HTTP verbs
 * (GET, POST, PUT, PATCH, DELETE, etc.) and supports headers, JSON, form, and file payloads.
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
     * Creates a new ErlenClient instance.
     *
     * @param App $app The application instance to handle requests.
     */
    public function __construct(App $app)
    {
        $this->app = $app;
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
     * Supports headers, query strings, JSON, form data, and file uploads.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $uri The request URI.
     * @param array $options Additional request options:
     *   - headers: associative array of HTTP headers
     *   - query: query parameters
     *   - json: data to be JSON-encoded
     *   - form_params: form data
     *   - files: uploaded file data
     *   - body: raw request body
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
            'SCRIPT_NAME' => '',
            'PHP_SELF' => $path,
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTP_HOST' => 'localhost',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
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
            $body = json_encode($json, JSON_UNESCAPED_UNICODE);
        } elseif (!empty($form)) {
            $body = http_build_query($form);
        }

        // Add query parameters if provided
        $queryParams = $options['query'] ?? [];
        if (!empty($queryParams)) {
            $queryString = http_build_query($queryParams);
            $uri .= (str_contains($uri, '?') ? '&' : '?') . $queryString;
            $server['REQUEST_URI'] = $uri;
            $server['QUERY_STRING'] = $queryString;
        }

        // Create Request and Response instances
        $request = new Request(
            $server,
            $queryParams,
            $form,
            $files,
            'php://memory',
            $body
        );

        $response = new Response();

        // Delegate to the application
        return $this->app->handle($request, $response);
    }

    /**
     * Normalizes a URI in the same way as the App class.
     *
     * Removes trailing slashes except for the root route, preserving
     * query strings and fragments.
     *
     * @param string $uri The URI to normalize.
     * @return string Normalized URI.
     */
    private function normalizeUri(string $uri): string
    {
        $parsed = parse_url($uri);
        $path = $parsed['path'] ?? '/';

        if ($path !== '/') {
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
