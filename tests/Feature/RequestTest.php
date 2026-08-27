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

test('request handles forwarded IP when proxy is trusted', function () {
    Request::setTrustedProxies(['127.0.0.1']);
    $request = new Request([
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '10.0.0.5, 192.168.1.1',
    ]);
    Request::setTrustedProxies([]);
    expect($request->getIp())->toBe('10.0.0.5');
});

test('request ignores X-Forwarded-For when proxy is not trusted', function () {
    Request::setTrustedProxies([]);
    $request = new Request([
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_X_FORWARDED_FOR' => '10.0.0.5',
    ]);
    expect($request->getIp())->toBe('1.2.3.4');
});

test('request ignores X-Forwarded-For even from known IP when not in trusted list', function () {
    Request::setTrustedProxies(['192.168.1.1']);
    $request = new Request([
        'REMOTE_ADDR' => '1.2.3.4',
        'HTTP_X_FORWARDED_FOR' => '10.0.0.5',
    ]);
    Request::setTrustedProxies([]);
    expect($request->getIp())->toBe('1.2.3.4');
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

// -------------------------------------------------------------------------
// Headers
// -------------------------------------------------------------------------

test('request getHeader returns value case-insensitively', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_X_CUSTOM' => 'test-value']);
    expect($req->getHeader('x-custom'))->toBe('test-value')
        ->and($req->getHeader('X-Custom'))->toBe('test-value');
});

test('request getHeader returns null for missing header', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
    expect($req->getHeader('x-missing'))->toBeNull();
});

test('request getHeaders returns all parsed headers', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_ACCEPT' => 'application/json']);
    expect($req->getHeaders())->toHaveKey('accept', 'application/json');
});

test('request hasHeader returns true for existing header', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_ACCEPT' => 'text/html']);
    expect($req->hasHeader('accept'))->toBeTrue()
        ->and($req->hasHeader('Accept'))->toBeTrue();
});

test('request hasHeader returns false for missing header', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
    expect($req->hasHeader('x-missing'))->toBeFalse();
});

test('request parses CONTENT_TYPE server var as header', function () {
    $req = new Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'CONTENT_TYPE' => 'application/json']);
    expect($req->getHeader('content-type'))->toBe('application/json');
});

// -------------------------------------------------------------------------
// Method override
// -------------------------------------------------------------------------

test('request overrides method via _method POST field', function () {
    $req = new Request(
        ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/resource'],
        [],
        ['_method' => 'PUT']
    );
    expect($req->getMethod())->toBe('PUT');
});

test('request overrides method via X-HTTP-Method-Override header', function () {
    $req = new Request(
        ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/resource', 'HTTP_X_HTTP_METHOD_OVERRIDE' => 'DELETE'],
        [],
        []
    );
    expect($req->getMethod())->toBe('DELETE');
});

// -------------------------------------------------------------------------
// Body & files
// -------------------------------------------------------------------------

test('request getRawBody returns the raw body string', function () {
    $req = new Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'], [], [], [], 'php://memory', 'raw content');
    expect($req->getRawBody())->toBe('raw content');
});

test('request getFile returns file by numeric index for multi-upload', function () {
    $files = [
        'docs' => [
            'name'     => ['a.txt', 'b.txt'],
            'type'     => ['text/plain', 'text/plain'],
            'tmp_name' => ['/tmp/x', '/tmp/y'],
            'error'    => [0, 0],
            'size'     => [10, 20],
        ],
    ];
    $req = new Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'], [], [], $files);
    $file = $req->getFile('docs', 1);
    expect($file['name'])->toBe('b.txt')
        ->and($file['size'])->toBe(20);
});

test('request getJsonError returns null when JSON is valid', function () {
    $req = new Request(
        ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'CONTENT_TYPE' => 'application/json'],
        [], [], [], 'php://memory',
        '{"ok": true}'
    );
    expect($req->getJsonError())->toBeNull();
});

// -------------------------------------------------------------------------
// isAjax / isSecure
// -------------------------------------------------------------------------

test('request isAjax returns true for XMLHttpRequest header', function () {
    $req = new Request([
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);
    expect($req->isAjax())->toBeTrue();
});

test('request isAjax returns false for regular requests', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
    expect($req->isAjax())->toBeFalse();
});

test('request isSecure returns true when HTTPS is on', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTPS' => 'on']);
    expect($req->isSecure())->toBeTrue();
});

test('request isSecure returns true on port 443', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'SERVER_PORT' => '443']);
    expect($req->isSecure())->toBeTrue();
});

test('request isSecure returns false for plain HTTP', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTPS' => 'off', 'SERVER_PORT' => '80']);
    expect($req->isSecure())->toBeFalse();
});

// -------------------------------------------------------------------------
// getUserAgent
// -------------------------------------------------------------------------

test('request getUserAgent returns user agent string', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_USER_AGENT' => 'TestAgent/1.0']);
    expect($req->getUserAgent())->toBe('TestAgent/1.0');
});

test('request getUserAgent returns null when not set', function () {
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
    expect($req->getUserAgent())->toBeNull();
});
