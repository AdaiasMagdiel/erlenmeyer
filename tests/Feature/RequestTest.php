<?php

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

beforeEach(function () {
    $this->app = new App();
});

describe('Request class', function () {
    it('initializes correctly with default values', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'],
            get: ['id' => '1'],
            post: [],
            files: []
        );

        expect($request->getMethod())->toBe('GET');
        expect($request->getUri())->toBe('/test');
        expect($request->getQueryParams())->toEqual(['id' => '1']);
        expect($request->getFormData())->toEqual([]);
        expect($request->getFiles())->toEqual([]);
        expect($request->getRawBody())->toBeNull();
        expect($request->getIp())->toBeEmpty();
        expect($request->getUserAgent())->toBeNull();
    });

    it('handles headers correctly', function () {
        $server = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CUSTOM' => 'CustomValue'
        ];
        $request = new Request(server: $server);

        expect($request->getHeaders())->toEqual([
            'Accept' => 'application/json',
            'X-Custom' => 'CustomValue'
        ]);
        expect($request->getHeader('Accept'))->toBe('application/json');
        expect($request->getHeader('X-Custom'))->toBe('CustomValue');
        expect($request->getHeader('Non-Existent'))->toBeNull();
        expect($request->hasHeader('Accept'))->toBeTrue();
        expect($request->hasHeader('Non-Existent'))->toBeFalse();
    });

    it('handles method override via POST _method', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            post: ['_method' => 'PUT']
        );
        expect($request->getMethod())->toBe('PUT');

        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            post: ['_method' => 'DELETE']
        );
        expect($request->getMethod())->toBe('DELETE');

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET'],
            post: ['_method' => 'PUT']
        );
        expect($request->getMethod())->toBe('GET'); // Override only for POST
    });

    it('sanitizes URI correctly', function () {
        $request = new Request(server: ['REQUEST_URI' => '/test?param=1']);
        expect($request->getUri())->toBe('/test');

        $request = new Request(server: []);
        expect($request->getUri())->toBe('/');
    });

    it('handles query parameters with sanitization', function () {
        $request = new Request(get: ['name' => '<script>alert("xss")</script>', 'id' => '123']);
        expect($request->getQueryParams())->toEqual([
            'name' => '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            'id' => '123'
        ]);
        expect($request->getQueryParam('name'))->toBe('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
        expect($request->getQueryParam('id'))->toBe('123');
        expect($request->getQueryParam('nonexistent', 'default'))->toBe('default');
    });

    it('handles form data with sanitization', function () {
        $request = new Request(post: ['username' => '<p>user</p>', 'password' => 'secret']);
        expect($request->getFormData())->toEqual([
            'username' => '&lt;p&gt;user&lt;/p&gt;',
            'password' => 'secret'
        ]);
        expect($request->getFormDataParam('username'))->toBe('&lt;p&gt;user&lt;/p&gt;');
        expect($request->getFormDataParam('password'))->toBe('secret');
        expect($request->getFormDataParam('nonexistent', 'default'))->toBe('default');
    });

    it('handles JSON body with valid and invalid content', function () {
        $this->app->post('/json', function (Request $req, Response $res) {
            try {
                $data = $req->getJson(true, true);
                $res->withJson(['data' => $data ?? 'null'])->send();
            } catch (\RuntimeException $e) {
                $res->setStatusCode(400)->withJson(['error' => $e->getMessage()])->send();
            }
        });

        // Valid JSON
        $response = RequestSimulator::postJson($this->app, '/json', ['key' => 'value']);
        expect($response['status'])->toBe(200);
        expect(str_replace(["\n", "\r", "\t", "    "], '', $response['body']))->toBe('{"data": {"key": "value"}}');

        // Invalid JSON with ignoreContentType = true
        $response = RequestSimulator::post($this->app, '/json', [], [], [], ['HTTP_CONTENT_TYPE' => 'text/plain'], '{"key": "value"');
        expect($response['status'])->toBe(200);
        expect(str_replace(["\n", "\r", "\t", "    "], '', $response['body']))->toBe('{"data": "null"}');

        // Valid JSON with incorrect Content-Type and ignoreContentType = false
        $this->app->post('/json-strict', function (Request $req, Response $res) {
            try {
                $data = $req->getJson(true, false);
                $res->withJson(['data' => $data])->send();
            } catch (\RuntimeException $e) {
                $res->setStatusCode(400)->withJson(['error' => $e->getMessage()])->send();
            }
        });
        $response = RequestSimulator::post($this->app, '/json-strict', [], [], [], ['HTTP_CONTENT_TYPE' => 'text/plain'], '{"key":"value"}');
        expect($response['status'])->toBe(400);
        expect(str_replace(["\n", "\r", "\t", "    "], '', $response['body']))->toContain('Invalid Content-Type');

        // JSON as object (assoc = false)
        $response = RequestSimulator::post($this->app, '/json', [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"key":"value"}');
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'HTTP_CONTENT_TYPE' => 'application/json'],
            inputStream: 'data://text/plain,{"key":"value"}'
        );
        $jsonObject = $request->getJson(false);
        expect($jsonObject)->toBeInstanceOf(stdClass::class);
        expect($jsonObject->key)->toBe('value');
    });

    it('handles JSON error reporting', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'HTTP_CONTENT_TYPE' => 'application/json'],
            inputStream: 'data://text/plain,{"key": "value"'
        );
        expect($request->getJsonError())->toContain('Syntax error');
        expect($request->getJson())->toBeNull();
    })->throws(RuntimeException::class);

    it('handles raw body correctly', function () {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            inputStream: 'data://text/plain,Hello World'
        );
        expect($request->getRawBody())->toBe('Hello World');

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        expect($request->getRawBody())->toBeNull();
    });

    it('handles uploaded files', function () {
        $files = [
            'image' => [
                'name' => ['file1.jpg', 'file2.png'],
                'type' => ['image/jpeg', 'image/png'],
                'tmp_name' => ['/tmp/php123', '/tmp/php456'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [1024, 2048]
            ]
        ];
        $request = new Request(files: $files);

        expect($request->getFiles())->toEqual($files);
        expect($request->getFile('image'))->toEqual($files['image']);
        expect($request->getFile('image', 0))->toEqual([
            'name' => 'file1.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/php123',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ]);
        expect($request->getFile('image', 1))->toEqual([
            'name' => 'file2.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/php456',
            'error' => UPLOAD_ERR_OK,
            'size' => 2048
        ]);
        expect($request->getFile('nonexistent'))->toBeNull();
    });

    it('handles client IP address', function () {
        $request = new Request(server: ['REMOTE_ADDR' => '192.168.1.1']);
        expect($request->getIp())->toBe('192.168.1.1');

        $request = new Request(server: ['HTTP_X_FORWARDED_FOR' => 'invalid-ip']);
        expect($request->getIp())->toBeEmpty();
    });

    it('detects AJAX requests', function () {
        $request = new Request(server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        expect($request->isAjax())->toBeTrue();

        $request = new Request(server: ['HTTP_X_REQUESTED_WITH' => 'Other']);
        expect($request->isAjax())->toBeFalse();

        $request = new Request(server: []);
        expect($request->isAjax())->toBeFalse();
    });

    it('detects secure requests', function () {
        $request = new Request(server: ['HTTPS' => 'on']);
        expect($request->isSecure())->toBeTrue();

        $request = new Request(server: ['SERVER_PORT' => 443]);
        expect($request->isSecure())->toBeTrue();

        $request = new Request(server: ['HTTPS' => 'off', 'SERVER_PORT' => 80]);
        expect($request->isSecure())->toBeFalse();

        $request = new Request(server: []);
        expect($request->isSecure())->toBeFalse();
    });
});
