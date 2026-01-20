<?php

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;
use AdaiasMagdiel\Erlenmeyer\Logging\ConsoleLogger;
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;
use AdaiasMagdiel\Erlenmeyer\Testing\ErlenClient;

beforeEach(function () {
    $this->logger = new ConsoleLogger([LogLevel::INFO]);
});

test('app can be instantiated without logger', function () {
    $app = new App(null);
    expect($app)->toBeInstanceOf(App::class);
});

test('app registers GET route via proxy', function () {
    $app = new App($this->logger);
    $app->get('/test', function (Request $req, Response $res) {
        $res->withText('GET route works');
    });

    $client = new ErlenClient($app);
    $response = $client->get('/test');

    expect($response->getBody())->toBe('GET route works');
});

test('app registers POST route via proxy', function () {
    $app = new App($this->logger);
    $app->post('/test', function (Request $req, Response $res) {
        $res->withText('POST route works');
    });

    $client = new ErlenClient($app);
    $response = $client->post('/test');

    expect($response->getBody())->toBe('POST route works');
});

test('app handles route parameters correctly', function () {
    $app = new App($this->logger);
    $app->get('/users/[id]', function (Request $req, Response $res, $params) {
        $res->withJson(['user_id' => $params->id]);
    });

    $client = new ErlenClient($app);
    $response = $client->get('/users/123');

    expect($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toMatchArray(['user_id' => '123']);
});

test('app returns 404 for non-existent routes', function () {
    $app = new App($this->logger);
    $client = new ErlenClient($app);

    $response = $client->get('/non-existent-route');
    expect($response->getStatusCode())->toBe(404);
});

test('app handles custom 404 handler', function () {
    $app = new App($this->logger);
    $app->set404Handler(function (Request $req, Response $res) {
        $res->setStatusCode(404)->withJson(['error' => 'Custom 404']);
    });

    $client = new ErlenClient($app);
    $response = $client->get('/non-existent');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toMatchArray(['error' => 'Custom 404']);
});

test('app applies global middlewares', function () {
    $app = new App($this->logger);

    $app->addMiddleware(function (Request $req, Response $res, $next) {
        $res->setHeader('X-Middleware', 'applied');
        $next($req, $res, new stdClass());
    });

    $app->get('/test', function (Request $req, Response $res) {
        $res->withText('With middleware');
    });

    $client = new ErlenClient($app);
    $response = $client->get('/test');

    expect($response->getHeaders())->toHaveKey('X-Middleware', 'applied');
});

test('app handles redirects', function () {
    $app = new App($this->logger);
    $app->redirect('/old1', '/new1', true);
    $app->redirect('/old2', '/new2', false);

    $client = new ErlenClient($app);

    $response = $client->get('/old1');
    expect($response->getStatusCode())->toBe(301)
        ->and($response->getHeaders())->toHaveKey('Location', '/new1');

    $response = $client->get('/old2');
    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeaders())->toHaveKey('Location', '/new2');
});

test('app handles exceptions with custom handler', function () {
    $app = new App($this->logger);

    $app->setExceptionHandler(RuntimeException::class, function (Request $req, Response $res, Throwable $e) {
        $res->setStatusCode(500)->withJson(['error' => 'Custom handler: ' . $e->getMessage()]);
    });

    $app->get('/error', function () {
        throw new RuntimeException('Test error');
    });

    $client = new ErlenClient($app);
    $response = $client->get('/error');

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toHaveKey('error', 'Custom handler: Test error');
});

test('app handles multiple HTTP methods with any', function () {
    $app = new App($this->logger);
    $app->any('/any-route', function (Request $req, Response $res) {
        $res->withText('Any method works: ' . $req->getMethod());
    });

    $client = new ErlenClient($app);
    $methods = ['GET', 'POST', 'PUT', 'DELETE'];

    foreach ($methods as $method) {
        $response = $client->request($method, '/any-route');
        expect($response->getBody())->toBe("Any method works: $method");
    }
});

test('app handles route with and without trailing slash consistently', function () {
    $app = new App($this->logger);
    $app->get('/test', function (Request $req, Response $res) {
        $res->withText('Route matched');
    });

    $client = new ErlenClient($app);

    $response1 = $client->get('/test');
    expect($response1->getBody())->toBe('Route matched');

    $response2 = $client->get('/test/');
    expect($response2->getBody())->toBe('Route matched');
});

test('app sanitizes default error output', function () {
    $app = new App($this->logger);
    $app->get('/unsafe', function () {
        throw new Exception('<script>alert("XSS")</script>');
    });

    $client = new ErlenClient($app);
    $response = $client->get('/unsafe');

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getBody())->toContain('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;')
        ->and($response->getBody())->not->toContain('<script>alert("XSS")</script>');
});
