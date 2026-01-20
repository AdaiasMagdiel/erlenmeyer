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

test('request captures query parameters with dots preserved', function () {
    // Simulate what PHP sees in QUERY_STRING vs normalized keys
    $server = ['QUERY_STRING' => 'user.name=john&settings.theme=dark'];

    $request = new Request($server);
    $params = $request->getQueryParams();

    expect($params)->toHaveKey('user.name', 'john')
        ->and($params)->toHaveKey('settings.theme', 'dark')
        ->and($request->getQueryParam('user.name'))->toBe('john');
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

test('request handles forwarded IP correctly', function () {
    // Note: Based on the provided code, X-Forwarded-For IS trusted currently.
    $server = [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '10.0.0.5, 192.168.1.1'
    ];
    $request = new Request($server);
    expect($request->getIp())->toBe('10.0.0.5');
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

test('request handles nested array dot notation in query params', function () {
    // user.data[first.name]=John
    $server = ['QUERY_STRING' => 'user.data%5Bfirst.name%5D=John'];

    $request = new Request($server);
    $params = $request->getQueryParams();

    expect($params)->toHaveKey('user.data')
        ->and($params['user.data'])->toHaveKey('first.name', 'John');
});

test('request captures json error properly', function () {
    $server = ['CONTENT_TYPE' => 'application/json'];
    $body = '{"key": "value",}';

    $request = new Request($server, null, null, null, 'php://memory', $body);

    expect($request->getJsonError())->not->toBeNull()
        ->and(fn() => $request->getJson())->toThrow(RuntimeException::class);
});

test('request getJson ignores content type check when requested', function () {
    $server = ['CONTENT_TYPE' => 'text/plain'];
    $body = '{"key": "value"}';

    $request = new Request($server, null, null, null, 'php://memory', $body);

    expect($request->getJson(true, true))->toBe(['key' => 'value']);
});

test('request initClientInfo handles missing REMOTE_ADDR gracefully', function () {
    $server = [];
    $request = new Request($server);

    expect($request->getIp())->toBeNull();
});

test('request getJson handles lazy initialization caching', function () {
    $server = ['CONTENT_TYPE' => 'application/json'];
    $body = '{"a":1}';

    $request = new Request($server, null, null, null, 'php://memory', $body);

    $first = $request->getJson();
    $second = $request->getJson();

    expect($first)->toBe($second)
        ->and($first)->toBe(['a' => 1]);
});
