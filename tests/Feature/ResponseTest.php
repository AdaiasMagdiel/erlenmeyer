<?php

use AdaiasMagdiel\Erlenmeyer\Response;

beforeEach(function () {
    // Mock the header function for testing
    Response::updateFunctions(['header' => function ($header, $replace = true) {
        // Capture headers for assertion if needed
    }]);
});

test('response sets status code', function () {
    $response = new Response(201);
    expect($response->getStatusCode())->toBe(201);
});

test('response throws exception for invalid status code', function () {
    expect(fn() => new Response(999))
        ->toThrow(InvalidArgumentException::class);
});

test('response sets headers', function () {
    $response = new Response();
    $response->setHeader('X-Custom', 'value');
    expect($response->getHeaders())->toHaveKey('X-Custom', 'value');
});

test('response sets content type', function () {
    $response = new Response();
    $response->setContentType('application/json');
    expect($response->getContentType())->toBe('application/json')
        ->and($response->getHeaders())->toHaveKey('Content-Type', 'application/json');
});

test('response with JSON content', function () {
    $response = new Response();
    $data = ['name' => 'John', 'age' => 30];
    $response->withJson($data);

    expect($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toBe($data)
        ->and($response->getContentType())->toBe('application/json; charset=UTF-8');
});

test('response with file download', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, 'file content');

    $response = new Response();
    $response->withFile($tempFile);

    expect($response->getHeaders())->toHaveKey('Content-Disposition')
        ->and($response->getBody())->toBe('file content');

    unlink($tempFile);
});

test('response detects mime type for download', function () {
    // Simulate a file path ending in .json
    $tempFile = sys_get_temp_dir() . '/test_data.json';
    file_put_contents($tempFile, '{}');

    $response = new Response();
    $response->withFile($tempFile);

    expect($response->getContentType())->toBe('application/json');
    unlink($tempFile);
});

test('response throws exception when sending twice', function () {
    $response = new Response();
    $response->withText('Test');

    ob_start();
    $response->send();
    ob_end_clean();

    expect(fn() => $response->send())->toThrow(RuntimeException::class);
});

test('response throws exception if template file does not exist', function () {
    $response = new Response();
    expect(fn() => $response->withTemplate('/invalid/path/template.php'))
        ->toThrow(RuntimeException::class, 'Template not found');
});

test('response renders template with data', function () {
    $file = sys_get_temp_dir() . '/test_template.php';
    file_put_contents($file, 'Hello <?= $name ?>');

    $response = new Response();
    $response->withTemplate($file, ['name' => 'Erlenmeyer']);

    expect($response->getBody())->toBe('Hello Erlenmeyer');
    unlink($file);
});

test('response throws exception on json encoding failure', function () {
    $response = new Response();
    // NAN causes json_encode error
    $data = ['value' => NAN];

    expect(fn() => $response->withJson($data))
        ->toThrow(RuntimeException::class, 'Failed to serialize JSON');
});

test('response throws exception for invalid redirect status code', function () {
    $response = new Response();
    expect(fn() => $response->redirect('/home', 200))
        ->toThrow(InvalidArgumentException::class);
});

test('response allows removing header', function () {
    $response = new Response();
    $response->setHeader('X-Remove-Me', 'true');
    $response->removeHeader('X-Remove-Me');

    expect($response->getHeaders())->not->toHaveKey('X-Remove-Me');
});

test('response throws exception if modifying headers after sent', function () {
    $response = new Response();
    ob_start();
    $response->send();
    ob_end_clean();

    expect(fn() => $response->setHeader('X-Late', 'true'))
        ->toThrow(RuntimeException::class);
});

test('response throws exception if setting CORS after sent', function () {
    $response = new Response();
    ob_start();
    $response->send();
    ob_end_clean();

    expect(fn() => $response->setCORS(['origin' => '*']))
        ->toThrow(RuntimeException::class);
});

// -------------------------------------------------------------------------
// getJson
// -------------------------------------------------------------------------

test('response getJson returns decoded array', function () {
    $res = new Response();
    $res->withJson(['key' => 'value', 'num' => 42]);
    expect($res->getJson())->toBe(['key' => 'value', 'num' => 42]);
});

test('response getJson returns null when body is null', function () {
    $res = new Response();
    expect($res->getJson())->toBeNull();
});

test('response getJson returns null when body is invalid JSON', function () {
    $res = new Response();
    $res->setBody('not-json{');
    expect($res->getJson())->toBeNull();
});

// -------------------------------------------------------------------------
// clear
// -------------------------------------------------------------------------

test('response clear resets headers, body and content type', function () {
    $res = new Response();
    $res->setHeader('X-Custom', 'value')->withText('some content');
    $res->clear();
    expect($res->getHeaders())->toBe([])
        ->and($res->getBody())->toBeNull()
        ->and($res->getContentType())->toBe('text/html');
});

test('response clear throws if already sent', function () {
    Response::updateFunctions(['header' => fn() => null]);
    $res = new Response();
    $res->withText('body');
    ob_start();
    $res->send();
    ob_end_clean();
    expect(fn() => $res->clear())->toThrow(RuntimeException::class);
});

// -------------------------------------------------------------------------
// withError
// -------------------------------------------------------------------------

test('response withError sets JSON error by default', function () {
    $res = new Response();
    $res->withError(422, 'Validation failed');
    expect($res->getStatusCode())->toBe(422)
        ->and($res->getJson())->toMatchArray(['error' => 'Validation failed']);
});

test('response withError sets plain text error when json is false', function () {
    $res = new Response();
    $res->withError(400, 'Bad Request', false);
    expect($res->getStatusCode())->toBe(400)
        ->and($res->getBody())->toBe('Bad Request');
});

test('response withError invokes logger callback', function () {
    $res = new Response();
    $logged = [];
    $res->withError(500, 'Internal error', true, function (int $code, string $msg) use (&$logged) {
        $logged = [$code, $msg];
    });
    expect($logged)->toBe([500, 'Internal error']);
});

test('response withError with empty message only sets status code', function () {
    $res = new Response();
    $res->withError(503);
    expect($res->getStatusCode())->toBe(503)
        ->and($res->getBody())->toBeNull();
});

// -------------------------------------------------------------------------
// withCookie
// -------------------------------------------------------------------------

test('response withCookie sets cookie with HttpOnly by default', function () {
    $res = new Response();
    $res->withCookie('session', 'abc123');
    $cookie = $res->getCookies()[0];
    expect($cookie)->toContain('session=')
        ->and($cookie)->toContain('abc123')
        ->and($cookie)->toContain('HttpOnly')
        ->and($cookie)->toContain('Path=/');
});

test('response withCookie includes all optional attributes', function () {
    $res = new Response();
    $expire = mktime(0, 0, 0, 1, 1, 2030);
    $res->withCookie('prefs', 'dark', $expire, '/app', 'example.com', true, true);
    $cookie = $res->getCookies()[0];
    expect($cookie)->toContain('Path=/app')
        ->and($cookie)->toContain('Domain=example.com')
        ->and($cookie)->toContain('Secure')
        ->and($cookie)->toContain('HttpOnly')
        ->and($cookie)->toContain('Expires=');
});

test('response withCookie supports multiple cookies independently', function () {
    $res = new Response();
    $res->withCookie('a', '1')->withCookie('b', '2')->withCookie('c', '3');
    $cookies = $res->getCookies();
    expect($cookies)->toHaveCount(3)
        ->and($cookies[0])->toContain('a=')
        ->and($cookies[1])->toContain('b=')
        ->and($cookies[2])->toContain('c=');
});

// -------------------------------------------------------------------------
// setCORS
// -------------------------------------------------------------------------

test('response setCORS sets origin from string', function () {
    $res = new Response();
    $res->setCORS(['origin' => 'https://example.com']);
    expect($res->getHeaders()['Access-Control-Allow-Origin'])->toBe('https://example.com');
});

test('response setCORS sets origin from array', function () {
    $res = new Response();
    $res->setCORS(['origin' => ['https://a.com', 'https://b.com']]);
    expect($res->getHeaders()['Access-Control-Allow-Origin'])->toBe('https://a.com, https://b.com');
});

test('response setCORS sets methods from array', function () {
    $res = new Response();
    $res->setCORS(['methods' => ['GET', 'POST', 'OPTIONS']]);
    expect($res->getHeaders()['Access-Control-Allow-Methods'])->toBe('GET, POST, OPTIONS');
});

test('response setCORS sets allowed headers from array', function () {
    $res = new Response();
    $res->setCORS(['headers' => ['Authorization', 'Content-Type']]);
    expect($res->getHeaders()['Access-Control-Allow-Headers'])->toBe('Authorization, Content-Type');
});

test('response setCORS sets credentials and max_age', function () {
    $res = new Response();
    $res->setCORS(['credentials' => true, 'max_age' => 3600]);
    expect($res->getHeaders()['Access-Control-Allow-Credentials'])->toBe('true')
        ->and($res->getHeaders()['Access-Control-Max-Age'])->toBe('3600');
});

test('response setCORS methods as string', function () {
    $res = new Response();
    $res->setCORS(['methods' => 'GET, POST']);
    expect($res->getHeaders()['Access-Control-Allow-Methods'])->toBe('GET, POST');
});

// -------------------------------------------------------------------------
// constructor
// -------------------------------------------------------------------------

test('response constructor accepts initial status and headers', function () {
    $res = new Response(201, ['X-Custom' => 'hello', 'X-Other' => 'world']);
    expect($res->getStatusCode())->toBe(201)
        ->and($res->getHeaders()['X-Custom'])->toBe('hello')
        ->and($res->getHeaders()['X-Other'])->toBe('world');
});
