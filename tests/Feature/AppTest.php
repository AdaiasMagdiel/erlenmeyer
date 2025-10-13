<?php

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Assets;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;
use AdaiasMagdiel\Erlenmeyer\Logging\ConsoleLogger;
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;
use AdaiasMagdiel\Erlenmeyer\Testing\ErlenClient;

beforeEach(function () {
    $dir = dirname(__DIR__) . '/fixtures/public';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $this->logger = new ConsoleLogger([LogLevel::INFO]);
    $this->assets = new Assets($dir, '/assets');
});

test('app can be instantiated without logger', function () {
    $app = new App($this->assets, null);

    expect($app)->toBeInstanceOf(App::class);
});

test('app can be instantiated without assets', function () {
    $app = new App(null, $this->logger);

    expect($app)->toBeInstanceOf(App::class);
});

test('app throws exception for invalid assets directory', function () {
    expect(function () {
        $invalidAssets = new Assets('/invalid/directory');
        new App($invalidAssets, $this->logger);
    })->toThrow(InvalidArgumentException::class);
});

test('app registers GET route', function () {
    $app = new App(null, $this->logger);

    $app->get('/test', function (Request $req, Response $res) {
        $res->withText('GET route works');
    });

    $client = new ErlenClient($app);
    $response = $client->get('/test');

    expect($response->getBody())->toBe('GET route works');
});

test('app registers POST route', function () {
    $app = new App(null, $this->logger);

    $app->post('/test', function (Request $req, Response $res) {
        $res->withText('POST route works');
    });

    $client = new ErlenClient($app);
    $response = $client->post('/test');

    expect($response->getBody())->toBe('POST route works');
});

test('app handles route parameters', function () {
    $app = new App(null, $this->logger);

    $app->get('/users/[id]', function (Request $req, Response $res, $params) {
        $res->withJson(['user_id' => $params->id]);
    });

    $client = new ErlenClient($app);
    $response = $client->get('/users/123');

    expect($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toMatchArray(['user_id' => '123']);
});

test('app returns 404 for non-existent routes', function () {
    $app = new App(null, $this->logger);

    $client = new ErlenClient($app);
    $response = $client->get('/non-existent-route');

    expect($response->getStatusCode())->toBe(404);
});

test('app handles custom 404 handler', function () {
    $app = new App(null, $this->logger);

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
    $app = new App(null, $this->logger);

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
    $app = new App(null, $this->logger);

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
    $app = new App(null, $this->logger);

    $app->setExceptionHandler(RuntimeException::class, function (Request $req, Response $res, Exception $e) {
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
    $app = new App(null, $this->logger);

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

test('app handles root route', function () {
    $app = new App(null, $this->logger);

    $app->get('/', function (Request $req, Response $res) {
        $res->withText('Root route');
    });

    $client = new ErlenClient($app);
    $response = $client->get('/');

    expect($response->getBody())->toBe('Root route');
});

test('app handles route with and without trailing slash consistently', function () {
    $app = new App(null, $this->logger);

    $app->get('/test', function (Request $req, Response $res) {
        $res->withText('Route matched');
    });

    $client = new ErlenClient($app);

    $response1 = $client->get('/test');
    expect($response1->getBody())->toBe('Route matched');

    $response2 = $client->get('/test/');
    expect($response2->getBody())->toBe('Route matched');
});

test('app handles complex route patterns', function () {
    $app = new App(null, $this->logger);

    $app->get('/users/[id]/posts/[slug]', function (Request $req, Response $res, $params) {
        $res->withJson([
            'user_id' => $params->id,
            'post_slug' => $params->slug
        ]);
    });

    $client = new ErlenClient($app);
    $response = $client->get('/users/123/posts/my-first-post');

    expect($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toMatchArray([
            'user_id' => '123',
            'post_slug' => 'my-first-post'
        ]);
});

test('app handles asset requests correctly', function () {
    $testDir = dirname(__DIR__) . '/fixtures/public';

    file_put_contents($testDir . '/test.txt', 'Test asset content');

    $app = new App($this->assets, $this->logger);

    $app->get('/api/test', function (Request $req, Response $res) {
        $res->withJson(['api' => 'works']);
    });

    $client = new ErlenClient($app);

    $apiResponse = $client->get('/api/test');
    expect($apiResponse->getBody())->toBeJson()
        ->and(json_decode($apiResponse->getBody(), true))->toHaveKey('api', 'works');

    unlink($testDir . '/test.txt');
});

test('app differentiates between similar routes', function () {
    $app = new App(null, $this->logger);

    $app->get('/api/users', function (Request $req, Response $res) {
        $res->withText('Users list');
    });

    $app->get('/api/users/[id]', function (Request $req, Response $res, $params) {
        $res->withText('User details: ' . $params->id);
    });

    $client = new ErlenClient($app);

    $response1 = $client->get('/api/users');
    expect($response1->getBody())->toBe('Users list');

    $response2 = $client->get('/api/users/123');
    expect($response2->getBody())->toBe('User details: 123');
});

test('app run() executes and handles uncaught exceptions', function () {
    $app = new App(null, $this->logger);
    $app->get('/', fn() => throw new Exception('boom'));
    ob_start();
    $app->run();
    $output = ob_get_clean();

    expect($output)->toContain('500 Internal Server Error');
});

test('app returns correct handler for subclass exception', function () {
    $app = new App(null, $this->logger);
    $app->setExceptionHandler(Exception::class, fn() => 'handled');

    $handler = $app->getExceptionHandler(new RuntimeException('test'));
    expect($handler)->not->toBeNull();
});

test('app supports all HTTP methods', function () {
    $app = new App(null, $this->logger);
    $app->put('/put', fn($r, $s) => $s->withText('PUT'));
    $app->delete('/del', fn($r, $s) => $s->withText('DELETE'));
    $app->patch('/patch', fn($r, $s) => $s->withText('PATCH'));
    $app->options('/opt', fn($r, $s) => $s->withText('OPTIONS'));
    $app->head('/head', fn($r, $s) => $s->withText('HEAD'));
    $app->match(['GET', 'POST'], '/multi', fn($r, $s) => $s->withText('MULTI'));

    $client = new ErlenClient($app);
    expect($client->put('/put')->getBody())->toBe('PUT');
    expect($client->delete('/del')->getBody())->toBe('DELETE');
});

test('app applies multiple middlewares in correct order', function () {
    $app = new App(null, $this->logger);
    $order = [];

    $app->addMiddleware(function ($req, $res, $next) use (&$order) {
        $order[] = 'first';
        $next($req, $res, new stdClass());
        $order[] = 'after first';
    });

    $app->addMiddleware(function ($req, $res, $next) use (&$order) {
        $order[] = 'second';
        $next($req, $res, new stdClass());
        $order[] = 'after second';
    });

    $app->get('/mw', fn($r, $s) => $s->withText('OK'));
    $client = new ErlenClient($app);
    $client->get('/mw');

    expect($order)->toBe(['first', 'second', 'after second', 'after first']);
});

test('app uses fallback handler when defined', function () {
    $app = new App(null, $this->logger);
    $called = false;
    $app->set404Handler(fn() => null);
    $app->addMiddleware(fn($r, $s, $next) => $next($r, $s, new stdClass()));

    // Sobrescreve o fallback
    $reflection = new ReflectionClass($app);
    $prop = $reflection->getProperty('routes');
    $prop->setAccessible(true);
    $routes = $prop->getValue($app);
    $routes['fallback'] = function () use (&$called) {
        $called = true;
    };
    $prop->setValue($app, $routes);

    $client = new ErlenClient($app);
    $client->get('/not-found');

    expect($called)->toBeTrue();
});
