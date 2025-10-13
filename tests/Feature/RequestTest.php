<?php

use AdaiasMagdiel\Erlenmeyer\Request;

test('request captures method correctly', function () {
    $server = ['REQUEST_METHOD' => 'POST'];
    $request = new Request($server);

    expect($request->getMethod())->toBe('POST');
});

test('request captures URI correctly', function () {
    $server = ['REQUEST_URI' => '/test?param=value'];
    $request = new Request($server);

    expect($request->getUri())->toBe('/test');
});

test('request captures query parameters', function () {
    $get = ['page' => '1', 'search' => 'test'];
    $request = new Request(null, $get);

    expect($request->getQueryParams())->toBe($get)
        ->and($request->getQueryParam('page'))->toBe('1')
        ->and($request->getQueryParam('non-existent', 'default'))->toBe('default');
});

test('request captures form data', function () {
    $post = ['name' => 'John', 'email' => 'john@example.com'];
    $request = new Request(null, null, $post);

    expect($request->getFormData())->toBe($post)
        ->and($request->getFormDataParam('name'))->toBe('John');
});

test('request captures JSON body', function () {
    $jsonData = ['title' => 'Test', 'active' => true];
    $rawBody = json_encode($jsonData);

    $server = [
        'HTTP_CONTENT_TYPE' => 'application/json',
        'CONTENT_TYPE' => 'application/json'
    ];
    $request = new Request($server, null, null, null, 'php://memory', $rawBody);

    expect($request->getJson())->toBe($jsonData);
});

test('request detects AJAX requests', function () {
    $server = ['HTTP_X_REQUESTED_WITH' => 'xmlhttprequest'];
    $request = new Request($server);

    expect($request->isAjax())->toBeTrue();
});

test('request detects secure connections', function () {
    $server = ['HTTPS' => 'on'];
    $request = new Request($server);

    expect($request->isSecure())->toBeTrue();
});

test('request captures headers', function () {
    $server = [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_USER_AGENT' => 'TestAgent'
    ];
    $request = new Request($server);

    expect($request->getHeader('Accept'))->toBe('application/json')
        ->and($request->hasHeader('User-Agent'))->toBeTrue();
});

test('request captures files', function () {
    $files = [
        'avatar' => [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/php123',
            'error' => 0,
            'size' => 1024
        ]
    ];
    $request = new Request(null, null, null, $files);

    expect($request->getFiles())->toBe($files)
        ->and($request->getFile('avatar'))->toBe($files['avatar']);
});

test('request captures client IP', function () {
    $server = ['REMOTE_ADDR' => '192.168.1.1'];
    $request = new Request($server);

    expect($request->getIp())->toBe('192.168.1.1');
});

test('request handles method override from POST', function () {
    $server = ['REQUEST_METHOD' => 'POST'];
    $post = ['_method' => 'PUT'];
    $request = new Request($server, null, $post);

    expect($request->getMethod())->toBe('PUT');
});

test('request handles empty JSON body', function () {
    $server = [
        'HTTP_CONTENT_TYPE' => 'application/json',
        'CONTENT_TYPE' => 'application/json'
    ];
    $request = new Request($server, null, null, null, 'php://memory', '');


    expect($request->getJson(true, true))->toBeNull();
});

test('request handles invalid JSON gracefully', function () {

    $server = [
        'HTTP_CONTENT_TYPE' => 'application/json',
        'CONTENT_TYPE' => 'application/json'
    ];
    $request = new Request($server, null, null, null, 'php://memory', 'invalid json');


    $error = $request->getJsonError();


    $result = $request->getJson(true, true);

    expect($error)->not->toBeNull()
        ->and($result)->toBeNull();
});


test('request returns null for JSON with ignoreContentType and invalid JSON', function () {
    $server = [];
    $request = new Request($server, null, null, null, 'php://memory', 'invalid json');


    expect($request->getJson(true, true))->toBeNull();
});

test('request throws exception for JSON without proper Content-Type', function () {
    $server = [];
    $request = new Request($server, null, null, null, 'php://memory', '{"test": "value"}');


    expect(fn() => $request->getJson())->toThrow(RuntimeException::class);
});

test('request handles JSON with ignoreContentType flag', function () {
    $jsonData = ['test' => 'value'];
    $rawBody = json_encode($jsonData);


    $request = new Request([], null, null, null, 'php://memory', $rawBody);

    expect($request->getJson(true, true))->toBe($jsonData);
});

test('request returns object when assoc is false', function () {
    $jsonData = ['test' => 'value'];
    $rawBody = json_encode($jsonData);

    $server = [
        'HTTP_CONTENT_TYPE' => 'application/json',
        'CONTENT_TYPE' => 'application/json'
    ];
    $request = new Request($server, null, null, null, 'php://memory', $rawBody);

    $result = $request->getJson(false);
    expect($result)->toBeObject()
        ->and($result->test)->toBe('value');
});

test('request handles multiple files with index', function () {
    $files = [
        'documents' => [
            'name' => ['file1.txt', 'file2.txt'],
            'type' => ['text/plain', 'text/plain'],
            'tmp_name' => ['/tmp/php123', '/tmp/php456'],
            'error' => [0, 0],
            'size' => [123, 456]
        ]
    ];
    $request = new Request(null, null, null, $files);

    $file1 = $request->getFile('documents', 0);
    $file2 = $request->getFile('documents', 1);

    expect($file1)->toHaveKey('name', 'file1.txt')
        ->and($file2)->toHaveKey('name', 'file2.txt');
});

test('request handles forwarded IP correctly', function () {


    $server = [
        'REMOTE_ADDR' => '192.168.1.100'
    ];
    $request = new Request($server);


    expect($request->getIp())->toBe('192.168.1.100');
});

test('request handles user agent correctly', function () {


    $server = ['HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser'];
    $request = new Request($server);

    expect($request->getUserAgent())->toBe('Mozilla/5.0 Test Browser');
});

test('request returns null for non-existent file', function () {
    $request = new Request();

    expect($request->getFile('non-existent'))->toBeNull()
        ->and($request->getFile('non-existent', 0))->toBeNull();
});

test('request returns null for non-existent header', function () {
    $request = new Request();

    expect($request->getHeader('Non-Existent-Header'))->toBeNull()
        ->and($request->hasHeader('Non-Existent-Header'))->toBeFalse();
});

test('request handles empty request', function () {
    $request = new Request();

    expect($request->getMethod())->toBe('GET')
        ->and($request->getUri())->toBe('/')
        ->and($request->getQueryParams())->toBe([])
        ->and($request->getFormData())->toBe([])
        ->and($request->getFiles())->toBe([]);
});

test('request initializes with minimal server data', function () {
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);

    expect($request->getMethod())->toBe('GET')
        ->and($request->getUri())->toBe('/test');
});

test('request handles raw body access', function () {
    $rawBody = 'raw content';
    $request = new Request([], null, null, null, 'php://memory', $rawBody);

    expect($request->getRawBody())->toBe('raw content');
});

test('request handles JSON error after initialization', function () {
    $server = [
        'HTTP_CONTENT_TYPE' => 'application/json',
        'CONTENT_TYPE' => 'application/json'
    ];
    $request = new Request($server, null, null, null, 'php://memory', 'invalid json');

    $error = $request->getJsonError();

    expect($error)->not->toBeNull();
});
