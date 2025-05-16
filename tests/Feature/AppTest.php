<?php

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Assets;
use AdaiasMagdiel\Erlenmeyer\Logging\FileLogger;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;
use AdaiasMagdiel\Erlenmeyer\Session;

it('can instantiate an Erlenmeyer application', function () {
    new App();
    expect(true)->toBeTrue();
});

it('can create an Erlenmeyer application with a logger', function () {
    $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "erlenmeyer_test_" . uniqid() . DIRECTORY_SEPARATOR;
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'info.log';

    $logger = new FileLogger(logDir: $logDir);
    new App(logger: $logger);

    expect(is_dir($logDir))->toBeTrue();
    expect(is_file($logFile))->toBeTrue();
    expect(filesize($logFile) > 0)->toBeTrue(0);

    unlink($logFile);
    rmdir($logDir);
});


it('can create an Erlenmeyer application with Assets management', function () {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "erlenmeyer_test_" . uniqid();
    $file = "test.txt";

    mkdir($dir);
    file_put_contents($dir . "/" . $file, "test");

    $assets = new Assets(assetsDirectory: $dir, assetsRoute: "/assets");
    $app = new App(assets: $assets);

    $data = RequestSimulator::get($app, "/assets/" . $file);

    expect($data["body"])->toBe("test");

    unlink($dir . "/" . $file);
    rmdir($dir);
});

it('handles a GET route with static response', function () {
    $app = new App();
    $app->get('/hello', function (Request $req, Response $res, $params) {
        $res->withHtml('Hello, World!')->send();
    });

    $data = RequestSimulator::get($app, '/hello');

    expect($data['status'])->toBe(200);
    expect($data['body'])->toBe('Hello, World!');
    expect($data['headers'])->toContain('Content-Type: text/html; charset=UTF-8');
});

it('handles a METHOD route with query params', function () {
    $app = new App();
    $app->get('/users', function (Request $req, Response $res, $params) {
        $page = $req->getQueryParam('page', 1);
        $res->withText("Page: $page")->send();
    });

    $data = RequestSimulator::get($app, '/users', ['page' => 42]);

    expect($data['status'])->toBe(200);
    expect($data['body'])->toBe('Page: 42');
});


it('handles a POST route with form data', function () {
    $app = new App();
    $app->post('/submit', function (Request $req, Response $res, $params) {
        $name = $req->getFormDataParam('name', 'Guess');
        $res->withHtml("Welcome, $name!")->setStatusCode(201)->send();
    });

    $data = RequestSimulator::post($app, '/submit', ['name' => 'Alice']);

    expect($data['status'])->toBe(201);
    expect($data['body'])->toBe('Welcome, Alice!');
});

it('handles dynamic routes with parameters', function () {
    $app = new App();
    $app->get('/user/[id]', function (Request $req, Response $res, $params) {
        $id = $params->id;
        $res->withHtml("User ID: $id")->send();
    });

    $data = RequestSimulator::get($app, '/user/123');

    expect($data['status'])->toBe(200);
    expect($data['body'])->toBe('User ID: 123');
});

it('returns 404 for unmatched routes', function () {
    $app = new App();

    $data = RequestSimulator::get($app, '/nonexistent');

    expect($data['status'])->toBe(404);
    expect($data['body'])->toContain('404 Not Found');
    expect($data['body'])->toContain('Requested URI: /nonexistent');
});

it('handles custom 404 handler', function () {
    $app = new App();
    $app->set404Handler(function (Request $req, Response $res, $params) {
        $res->setStatusCode(404)->withHtml('Custom 404: Page Not Found')->send();
    });

    $data = RequestSimulator::get($app, '/nonexistent');

    expect($data['status'])->toBe(404);
    expect($data['body'])->toBe('Custom 404: Page Not Found');
});

it('applies global middleware to all routes', function () {
    $app = new App();
    $app->addMiddleware(function (Request $req, Response $res, callable $next, $params) {
        $res->withHtml('Middleware executed: ');
        $next($req, $res, $params);
    });
    $app->get('/test', function (Request $req, Response $res, $params) {
        $res->withHtml($res->getBody() . 'Route handler')->send();
    });

    $data = RequestSimulator::get($app, '/test');

    expect($data['status'])->toBe(200);
    expect($data['body'])->toBe('Middleware executed: Route handler');
});

it('handles redirects', function () {
    $app = new App();
    $app->redirect('/old', '/new', false); // Temporary redirect (302)
    $app->get('/new', function (Request $req, Response $res, $params) {
        $res->withHtml('Redirected to new')->send();
    });

    $data = RequestSimulator::get($app, '/old');

    expect($data['status'])->toBe(302);
    expect($data['headers'])->toContain('Location: /new');
});

it('handles exceptions with custom handler', function () {
    $app = new App();
    $app->setExceptionHandler(\RuntimeException::class, function (Request $req, Response $res, \Exception $e) {
        $res->setStatusCode(500)->withHtml('Custom Error: ' . $e->getMessage())->send();
    });
    $app->get('/error', function (Request $req, Response $res, $params) {
        throw new \RuntimeException('Test error');
    });

    $data = RequestSimulator::get($app, '/error');

    expect($data['status'])->toBe(500);
    expect($data['body'])->toBe('Custom Error: Test error');
});

it('logs errors to file', function () {
    $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "erlenmeyer_test_" . uniqid() . DIRECTORY_SEPARATOR;
    $logFile = $logDir . 'info.log';

    $logger = new FileLogger(logDir: $logDir);
    $app = new App(logger: $logger);
    $app->get('/error', function (Request $req, Response $res, $params) {
        throw new \Exception('Test error');
    });

    $data = RequestSimulator::get($app, '/error');

    expect($data['status'])->toBe(500);
    expect(file_get_contents($logFile))->toContain('Test error');

    unlink($logFile);
    rmdir($logDir);
});

it('supports session management', function () {
    $app = new App();
    $app->get('/set-session', function (Request $req, Response $res, $params) {
        Session::set('user', 'Alice');
        $res->withHtml('Session set')->send();
    });
    $app->get('/get-session', function (Request $req, Response $res, $params) {
        $user = Session::get('user', 'Guest');
        $res->withHtml("User: $user")->send();
    });

    // Simulate setting session
    $data1 = RequestSimulator::get($app, '/set-session');
    expect($data1['status'])->toBe(200);
    expect($data1['body'])->toBe('Session set');

    // Simulate getting session (note: requires session persistence)
    $data2 = RequestSimulator::get($app, '/get-session', [], ['PHPSESSID' => session_id()]);
    expect($data2['status'])->toBe(200);
    expect($data2['body'])->toBe('User: Alice');
});

it('supports flash messages', function () {
    $app = new App();
    $app->get('/set-flash', function (Request $req, Response $res, $params) {
        Session::flash('message', 'Success!');
        $res->withHtml('Flash set')->send();
    });
    $app->get('/get-flash', function (Request $req, Response $res, $params) {
        $message = Session::getFlash('message', 'No message');
        $res->withHtml("Flash: $message")->send();
    });

    // Set flash message
    $data1 = RequestSimulator::get($app, '/set-flash');
    expect($data1['status'])->toBe(200);
    expect($data1['body'])->toBe('Flash set');

    // Get flash message
    $data2 = RequestSimulator::get($app, '/get-flash', [], ['PHPSESSID' => session_id()]);
    expect($data2['status'])->toBe(200);
    expect($data2['body'])->toBe('Flash: Success!');

    // Verify flash message is cleared
    $data3 = RequestSimulator::get($app, '/get-flash', [], ['PHPSESSID' => session_id()]);
    expect($data3['body'])->toBe('Flash: No message');
});

it('validates invalid HTTP methods', function () {
    $app = new App();

    expect(function () use ($app) {
        $app->route('INVALID', '/test', function (Request $req, Response $res, $params) {
            $res->withHtml('Test')->send();
        });
    })->toThrow(\InvalidArgumentException::class, 'Invalid HTTP method: INVALID');
});

it('handles PUT and DELETE routes', function () {
    $app = new App();
    $app->put('/update/[id]', function (Request $req, Response $res, $params) {
        $id = $params->id;
        $data = $req->getFormDataParam('data', 'No data');
        $res->withHtml("Updated ID: $id with $data")->send();
    });
    $app->delete('/delete/[id]', function (Request $req, Response $res, $params) {
        $id = $params->id;
        $res->withHtml("Deleted ID: $id")->send();
    });

    $data1 = RequestSimulator::put($app, '/update/456', ['data' => 'New value']);
    expect($data1['status'])->toBe(200);
    expect($data1['body'])->toBe('Updated ID: 456 with New value');

    $data2 = RequestSimulator::delete($app, '/delete/789');
    expect($data2['status'])->toBe(200);
    expect($data2['body'])->toBe('Deleted ID: 789');
});

it('handles routes with multiple parameters', function () {
    $app = new App();
    $app->get('/user/[id]/post/[postId]', function (Request $req, Response $res, $params) {
        $res->withHtml("User {$params->id}, Post {$params->postId}")->send();
    });

    $data = RequestSimulator::get($app, '/user/123/post/456');
    expect($data['body'])->toBe('User 123, Post 456');
});

it('handles ANY routes with multiple methods', function () {
    $app = new App();
    $app->any('/any-route', function (Request $req, Response $res, $params) {
        $res->withText("Handled {$req->getMethod()} request")->send();
    });

    $getData = RequestSimulator::get($app, '/any-route');
    $postData = RequestSimulator::post($app, '/any-route');

    expect($getData['body'])->toBe('Handled GET request');
    expect($postData['body'])->toBe('Handled POST request');
});

it('handles MATCH routes with specified methods', function () {
    $app = new App();
    $app->match(['GET', 'POST'], '/match-route', function (Request $req, Response $res, $params) {
        $res->withText("Matched {$req->getMethod()}")->send();
    });

    $getData = RequestSimulator::get($app, '/match-route');
    $postData = RequestSimulator::post($app, '/match-route');
    $putData = RequestSimulator::put($app, '/match-route'); // Should not match

    expect($getData['status'])->toBe(200);
    expect($postData['status'])->toBe(200);
    expect($putData['status'])->toBe(404);
});

it('applies route-specific middlewares', function () {
    $app = new App();
    $app->get('/admin', function (Request $req, Response $res, $params) {
        $res->withText($res->getBody() . 'Admin area')->send();
    }, [function (Request $req, Response $res, callable $next, $params) {
        $res->withText('Admin middleware: ');
        $next($req, $res, $params);
    }]);

    $data = RequestSimulator::get($app, '/admin');
    expect($data['body'])->toBe('Admin middleware: Admin area');
});

it('handles CORS headers', function () {
    $app = new App();
    $app->get('/api', function (Request $req, Response $res, $params) {
        $res->setCORS([
            'origin' => '*',
            'methods' => 'GET,POST',
            'headers' => 'Content-Type',
            'credentials' => true,
            'max_age' => 3600
        ])->withJson(['data' => 'test'])->send();
    });

    $data = RequestSimulator::get($app, '/api');

    // Verifica os headers CORS
    expect($data['headers'])->toContain('Access-Control-Allow-Origin: *');
    expect($data['headers'])->toContain('Access-Control-Allow-Methods: GET,POST');
    expect($data['headers'])->toContain('Access-Control-Allow-Headers: Content-Type');
    expect($data['headers'])->toContain('Access-Control-Allow-Credentials: true');
    expect($data['headers'])->toContain('Access-Control-Max-Age: 3600');

    // Verifica o corpo da resposta
    expect($data['body'])->toBe("{\n    \"data\": \"test\"\n}");
    expect($data['status'])->toBe(200);
});

it('handles file uploads', function () {
    $app = new App();
    $app->post('/upload', function (Request $req, Response $res, $params) {
        $file = $req->getFile('test_file');
        $res->withText("Received {$file['name']} ({$file['size']} bytes)")->send();
    });

    $tmpFile = sys_get_temp_dir() . '/test.txt';
    file_put_contents($tmpFile, 'test content');

    $data = RequestSimulator::post($app, '/upload', [], [], [
        'test_file' => [
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile)
        ]
    ]);

    expect($data['body'])->toBe('Received test.txt (12 bytes)');
    unlink($tmpFile);
});

it('handles middleware that stops execution', function () {
    $app = new App();
    $app->get('/test', function (Request $req, Response $res, $params) {
        $res->withText('Should not reach here')->send();
    }, [function (Request $req, Response $res, callable $next, $params) {
        $res->withText('Stopped by middleware')->send();
    }]);

    $data = RequestSimulator::get($app, '/test');
    expect($data['body'])->toBe('Stopped by middleware');
});

it('handles OPTIONS and PATCH routes', function () {
    $app = new App();
    $app->options('/api', function (Request $req, Response $res, $params) {
        $res->setCORS(['methods' => 'GET,POST'])->send();
    });
    $app->patch('/update', function (Request $req, Response $res, $params) {
        $res->withText('Patched')->send();
    });

    $data1 = RequestSimulator::simulateRequest($app, 'OPTIONS', '/api');
    expect($data1['headers'])->toContain('Access-Control-Allow-Methods: GET,POST');

    $data2 = RequestSimulator::simulateRequest($app, 'PATCH', '/update');
    expect($data2['body'])->toBe('Patched');
});

it('handles JSON body with valid and invalid content for POST and PUT', function () {
    $app = new App();
    $app->post('/json', function (Request $req, Response $res, $params) {
        $data = $req->getJson(assoc: true, ignoreContentType: true); // Test with ignoreContentType
        $res->withJson(['data' => $data ?? 'null'])->send();
    });
    $app->put('/json', function (Request $req, Response $res, $params) {
        $data = $req->getJson(assoc: true, ignoreContentType: true); // Test with ignoreContentType
        $res->withJson(['data' => $data ?? 'null'])->send();
    });

    // Valid JSON - POST
    $data1 = RequestSimulator::postJson($app, '/json', ['key' => 'value']);
    expect($data1['status'])->toBe(200);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data1['body']))->toBe('{"data": {"key": "value"}}');
    expect($data1['headers'])->toContain('Content-Type: application/json; charset=UTF-8');

    // Valid JSON - PUT
    $data2 = RequestSimulator::putJson($app, '/json', ['key' => 'value']);
    expect($data2['status'])->toBe(200);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data2['body']))->toBe('{"data": {"key": "value"}}');
    expect($data2['headers'])->toContain('Content-Type: application/json; charset=UTF-8');

    // Invalid JSON with POST and ignoreContentType
    $data3 = RequestSimulator::post($app, '/json', [], [], [], ['HTTP_CONTENT_TYPE' => 'text/plain'], '{"key": "value"');
    expect($data3['status'])->toBe(200);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data3['body']))->toBe('{"data": "null"}');

    // Invalid JSON with PUT and ignoreContentType
    $data4 = RequestSimulator::put($app, '/json', [], [], [], ['HTTP_CONTENT_TYPE' => 'text/plain'], '{"key": "value"');
    expect($data4['status'])->toBe(200);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data4['body']))->toBe('{"data": "null"}');

    // Empty body with POST
    $data5 = RequestSimulator::post($app, '/json', [], [], [], ['HTTP_CONTENT_TYPE' => 'text/plain'], '');
    expect($data5['status'])->toBe(200);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data5['body']))->toBe('{"data": "null"}');

    // Empty body with PUT
    $data6 = RequestSimulator::put($app, '/json', [], [], [], ['HTTP_CONTENT_TYPE' => 'text/plain'], '');
    expect($data6['status'])->toBe(200);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data6['body']))->toBe('{"data": "null"}');

    // Without Content-Type with POST
    $data7 = RequestSimulator::post($app, '/json', [], [], [], [], '{}');
    expect($data7['status'])->toBe(200);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data7['body']))->toBe('{"data": []}');

    // Without Content-Type with PUT
    $data8 = RequestSimulator::put($app, '/json', [], [], [], [], '{}');
    expect($data8['status'])->toBe(200);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data8['body']))->toBe('{"data": []}');

    // Valid JSON with incorrect Content-Type and ignoreContentType = false
    $app->post('/json-strict', function (Request $req, Response $res, $params) {
        try {
            $data = $req->getJson(true, false); // Strict Content-Type check
            $res->withJson(['data' => $data])->send();
        } catch (\RuntimeException $e) {
            $res->setStatusCode(400)->withJson(['error' => $e->getMessage()])->send();
        }
    });

    $data9 = RequestSimulator::post($app, '/json-strict', [], [], [], ['HTTP_CONTENT_TYPE' => 'text/plain'], '{"key":"value"}');
    expect($data9['status'])->toBe(400);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data9['body']))->toContain('Invalid Content-Type');

    $data10 = RequestSimulator::post($app, '/json-strict', [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"key":"value"');
    expect($data10['status'])->toBe(400);
    expect(str_replace(["\n", "\r", "\t", "    "], '', $data10['body']))->toContain('Failed to decode JSON');
});
