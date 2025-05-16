<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Response;

pest()->extend(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Class to mock php://input stream.
 */
class MockPhpInputStream
{
    public static $input = ''; // Raw input data
    private $position = 0;
    private $length;

    public $context; // Explicitly declare to avoid deprecation warning

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->length = strlen(self::$input);
        return true;
    }

    public function stream_read(int $count): string
    {
        if ($this->position >= $this->length) {
            return '';
        }
        $data = substr(self::$input, $this->position, $count);
        $this->position += strlen($data);
        return $data;
    }

    public function stream_eof(): bool
    {
        return $this->position >= $this->length;
    }

    public function stream_close(): void
    {
        // No resource to close since we're using a string
    }

    public function stream_stat(): array
    {
        return [];
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }
}

class RequestSimulator
{
    /**
     * Simulates a GET request.
     *
     * @param App $app The application instance.
     * @param string $uri The request URI (e.g., '/path/to/resource').
     * @param array $query Query parameters (e.g., ['id' => 1]).
     * @param array $server Additional server variables (e.g., ['HTTP_ACCEPT' => 'application/json']).
     * @return array Response data with status code, headers, and body.
     */
    public static function get(App $app, string $uri, array $query = [], array $server = []): array
    {
        return self::simulateRequest($app, 'GET', $uri, $query, [], [], $server);
    }

    /**
     * Simulates a POST request.
     *
     * @param App $app The application instance.
     * @param string $uri The request URI.
     * @param array $data POST data.
     * @param array $query Query parameters.
     * @param array $files Uploaded files.
     * @param array $server Additional server variables.
     * @param string $rawInput Raw input data (e.g., JSON string).
     * @return array Response data.
     */
    public static function post(App $app, string $uri, array $data = [], array $query = [], array $files = [], array $server = [], string $rawInput = ''): array
    {
        return self::simulateRequest($app, 'POST', $uri, $query, $data, $files, $server, $rawInput);
    }

    /**
     * Simulates a PUT request.
     *
     * @param App $app The application instance.
     * @param string $uri The request URI.
     * @param array $data PUT data.
     * @param array $query Query parameters.
     * @param array $files Uploaded files.
     * @param array $server Additional server variables.
     * @param string $rawInput Raw input data (e.g., JSON string).
     * @return array Response data.
     */
    public static function put(App $app, string $uri, array $data = [], array $query = [], array $files = [], array $server = [], string $rawInput = ''): array
    {
        return self::simulateRequest($app, 'PUT', $uri, $query, $data, $files, $server, $rawInput);
    }

    /**
     * Simulates a DELETE request.
     *
     * @param App $app The application instance.
     * @param string $uri The request URI.
     * @param array $query Query parameters.
     * @param array $server Additional server variables.
     * @return array Response data.
     */
    public static function delete(App $app, string $uri, array $query = [], array $server = []): array
    {
        return self::simulateRequest($app, 'DELETE', $uri, $query, [], [], $server);
    }

    /**
     * Simulates an HTTP request by setting superglobals and capturing the response.
     *
     * @param App $app The application instance.
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.).
     * @param string $uri Request URI.
     * @param array $query Query parameters.
     * @param array $data Body data (for POST, PUT, etc.).
     * @param array $files Uploaded files.
     * @param array $server Additional server variables.
     * @param string $rawInput Raw input data (e.g., JSON string).
     * @return array Response data with status code, headers, and body.
     */
    public static function simulateRequest(App $app, string $method, string $uri, array $query = [], array $data = [], array $files = [], array $server = [], string $rawInput = ''): array
    {
        // Reset superglobals
        $_GET = $query;
        $_POST = $method === 'POST' || $method === 'PUT' ? $data : [];
        $_FILES = $files;
        $_COOKIE = [];
        $_REQUEST = array_merge($_GET, $_POST);
        $_SERVER = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
            'QUERY_STRING' => http_build_query($query),
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'localhost',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate, br',
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'SCRIPT_NAME' => '/index.php',
            'HTTP_CONNECTION' => 'keep-alive',
        ];

        // Merge additional server variables, ensuring Content-Type is set for JSON
        if ($rawInput && !isset($server['HTTP_CONTENT_TYPE'])) {
            $server['HTTP_CONTENT_TYPE'] = 'application/json';
        }
        $_SERVER = array_merge($_SERVER, $server);

        // Simulate php://input for raw data
        if ($rawInput) {
            stream_wrapper_unregister('php');
            stream_wrapper_register('php', MockPhpInputStream::class);
            MockPhpInputStream::$input = $rawInput;
        }

        $headers = [];
        Response::updateFunctions([
            'header' => function (string $string, bool $replace = true, int $response_code = 0) use (&$headers) {
                $headers[] = $string;
                header($string, $replace, $response_code);
            }
        ]);

        // Capture output
        ob_start();
        try {
            $app->run();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $body = ob_get_clean();

        // Restore php://input
        if ($rawInput) {
            stream_wrapper_restore('php');
        }

        // Use captured headers or fallback to headers_list()
        $statusCode = http_response_code() ?: 200;

        return [
            'status' => $statusCode,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * Helper function to send POST request with JSON body.
     *
     * @param App $app The application instance.
     * @param string $uri The request URI.
     * @param mixed $data Data to encode as JSON.
     * @param array $server Additional server variables.
     * @return array Response data.
     */
    public static function postJson(App $app, string $uri, $data, array $server = ['HTTP_CONTENT_TYPE' => 'application/json']): array
    {
        return self::post($app, $uri, [], [], [], $server, json_encode($data));
    }

    /**
     * Helper function to send PUT request with JSON body.
     *
     * @param App $app The application instance.
     * @param string $uri The request URI.
     * @param mixed $data Data to encode as JSON.
     * @param array $server Additional server variables.
     * @return array Response data.
     */
    public static function putJson(App $app, string $uri, $data, array $server = ['HTTP_CONTENT_TYPE' => 'application/json']): array
    {
        return self::post($app, $uri, [], [], [], $server, json_encode($data));
    }

    /**
     * Helper function to send DELETE request.
     *
     * @param App $app The application instance.
     * @param string $uri The request URI.
     * @param array $query Query parameters.
     * @param array $server Additional server variables.
     * @return array Response data.
     */
    public static function deleteJson(App $app, string $uri, array $query = [], array $server = ['HTTP_CONTENT_TYPE' => 'application/json']): array
    {
        return self::delete($app, $uri, $query, $server);
    }
}
