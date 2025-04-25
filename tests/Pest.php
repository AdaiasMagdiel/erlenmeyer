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
use Mockery as m;

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
 * Simulates a GET request.
 *
 * @param App $app The application instance.
 * @param string $uri The request URI (e.g., '/path/to/resource').
 * @param array $query Query parameters (e.g., ['id' => 1]).
 * @param array $server Additional server variables (e.g., ['HTTP_ACCEPT' => 'application/json']).
 * @return array Response data with status code, headers, and body.
 */
function get(App $app, string $uri, array $query = [], array $server = []): array
{
    return simulateRequest($app, 'GET', $uri, $query, [], [], $server);
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
 * @return array Response data.
 */
function post(App $app, string $uri, array $data = [], array $query = [], array $files = [], array $server = []): array
{
    return simulateRequest($app, 'POST', $uri, $query, $data, $files, $server);
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
 * @return array Response data.
 */
function put(App $app, string $uri, array $data = [], array $query = [], array $files = [], array $server = []): array
{
    return simulateRequest($app, 'PUT', $uri, $query, $data, $files, $server);
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
function delete(App $app, string $uri, array $query = [], array $server = []): array
{
    return simulateRequest($app, 'DELETE', $uri, $query, [], [], $server);
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
 * @return array Response data with status code, headers, and body.
 */
function simulateRequest(App $app, string $method, string $uri, array $query = [], array $data = [], array $files = [], array $server = []): array
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

    // Merge additional server variables
    $_SERVER = array_merge($_SERVER, $server);

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

    // Use captured headers or fallback to headers_list()
    $statusCode = http_response_code() ?: 200;

    return [
        'status' => $statusCode,
        'headers' => $headers,
        'body' => $body,
    ];
}
