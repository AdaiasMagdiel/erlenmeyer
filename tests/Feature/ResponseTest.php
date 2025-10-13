<?php

use AdaiasMagdiel\Erlenmeyer\Response;

beforeEach(function () {
    // Mock the header function for testing
    Response::updateFunctions(['header' => function ($header, $replace = true) {
        // Capture headers for assertion
        $this->headers[] = $header;
    }]);

    $this->headers = [];
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

test('response with HTML content', function () {
    $response = new Response();
    $response->withHtml('<h1>Test</h1>');

    expect($response->getBody())->toBe('<h1>Test</h1>')
        ->and($response->getContentType())->toBe('text/html; charset=UTF-8');
});

test('response with JSON content', function () {
    $response = new Response();
    $data = ['name' => 'John', 'age' => 30];
    $response->withJson($data);

    expect($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toBe($data)
        ->and($response->getContentType())->toBe('application/json; charset=UTF-8');
});

test('response with text content', function () {
    $response = new Response();
    $response->withText('Plain text');

    expect($response->getBody())->toBe('Plain text')
        ->and($response->getContentType())->toBe('text/plain; charset=UTF-8');
});

test('response redirects', function () {
    $response = new Response();
    $response->redirect('/new-location', 301);

    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeaders())->toHaveKey('Location', '/new-location');
});

test('response sets cookies', function () {
    $response = new Response();
    $response->withCookie('session', 'abc123', 0, '/', '', false, true);

    $headers = $response->getHeaders();
    expect($headers['Set-Cookie'])->toContain('session=abc123');
});

test('response sends correctly', function () {
    $response = new Response(200, ['X-Test' => 'value']);
    $response->withText('Hello World');

    ob_start();
    $response->send();
    $output = ob_get_clean();

    expect($output)->toBe('Hello World')
        ->and($response->isSent())->toBeTrue();
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

test('response sets CORS headers', function () {
    $response = new Response();
    $response->setCORS([
        'origin' => '*',
        'methods' => ['GET', 'POST'],
        'headers' => 'Content-Type',
        'credentials' => true,
        'max_age' => 3600
    ]);

    $headers = $response->getHeaders();
    expect($headers)->toHaveKey('Access-Control-Allow-Origin', '*')
        ->and($headers)->toHaveKey('Access-Control-Allow-Methods', 'GET, POST')
        ->and($headers)->toHaveKey('Access-Control-Allow-Credentials', 'true');
});

test('response throws exception when sending twice', function () {
    $response = new Response();
    $response->withText('Test');

    ob_start();
    $response->send();
    ob_end_clean();

    expect(fn() => $response->send())->toThrow(RuntimeException::class);
});

test('response clears content and headers', function () {
    $response = new Response(201, ['X-Test' => 'value']);
    $response->withText('Test content');

    $response->clear();

    expect($response->getBody())->toBeNull()
        ->and($response->getHeaders())->toBeEmpty()
        ->and($response->getStatusCode())->toBe(201); // Status code should remain
});
