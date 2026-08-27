<?php

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;
use AdaiasMagdiel\Erlenmeyer\Testing\ErlenClient;

test('app can be instantiated without logger', function () {
    $app = new App(null);
    expect($app)->toBeInstanceOf(App::class);
});

test('app registers GET route via proxy', function () {
    $app = new App();
    $app->get('/test', function (Request $req, Response $res) {
        $res->withText('GET route works');
    });

    $client = new ErlenClient($app);
    $response = $client->get('/test');

    expect($response->getBody())->toBe('GET route works');
});

test('app registers POST route via proxy', function () {
    $app = new App();
    $app->post('/test', function (Request $req, Response $res) {
        $res->withText('POST route works');
    });

    $client = new ErlenClient($app);
    $response = $client->post('/test');

    expect($response->getBody())->toBe('POST route works');
});

test('app handles route parameters correctly', function () {
    $app = new App();
    $app->get('/users/[id]', function (Request $req, Response $res, $params) {
        $res->withJson(['user_id' => $params->id]);
    });

    $client = new ErlenClient($app);
    $response = $client->get('/users/123');

    expect($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toMatchArray(['user_id' => '123']);
});

test('app returns 404 for non-existent routes', function () {
    $app = new App();
    $client = new ErlenClient($app);

    $response = $client->get('/non-existent-route');
    expect($response->getStatusCode())->toBe(404);
});

test('app handles custom 404 handler', function () {
    $app = new App();
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
    $app = new App();

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
    $app = new App();
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
    $app = new App();

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
    $app = new App();
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
    $app = new App();
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
    $app = new App();
    $app->get('/unsafe', function () {
        throw new Exception('<script>alert("XSS")</script>');
    });

    $client = new ErlenClient($app);
    $response = $client->get('/unsafe');

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getBody())->toContain('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;')
        ->and($response->getBody())->not->toContain('<script>alert("XSS")</script>');
});

// -------------------------------------------------------------------------
// HTTP method proxies
// -------------------------------------------------------------------------

test('app registers PUT route via proxy', function () {
    $app = new App();
    $app->put('/resource', function (Request $req, Response $res) {
        $res->withText('PUT works');
    });
    $response = (new ErlenClient($app))->put('/resource');
    expect($response->getBody())->toBe('PUT works');
});

test('app registers DELETE route via proxy', function () {
    $app = new App();
    $app->delete('/resource', function (Request $req, Response $res) {
        $res->withText('DELETE works');
    });
    $response = (new ErlenClient($app))->delete('/resource');
    expect($response->getBody())->toBe('DELETE works');
});

test('app registers PATCH route via proxy', function () {
    $app = new App();
    $app->patch('/resource', function (Request $req, Response $res) {
        $res->withText('PATCH works');
    });
    $response = (new ErlenClient($app))->patch('/resource');
    expect($response->getBody())->toBe('PATCH works');
});

test('app registers OPTIONS route via proxy', function () {
    $app = new App();
    $app->options('/resource', function (Request $req, Response $res) {
        $res->withText('OPTIONS works');
    });
    $response = (new ErlenClient($app))->options('/resource');
    expect($response->getBody())->toBe('OPTIONS works');
});

test('app registers HEAD route via proxy', function () {
    $app = new App();
    $app->head('/resource', function (Request $req, Response $res) {
        $res->withText('HEAD works');
    });
    $response = (new ErlenClient($app))->head('/resource');
    expect($response->getStatusCode())->toBe(200);
});

test('app registers route via generic route() method', function () {
    $app = new App();
    $app->route('GET', '/generic', function (Request $req, Response $res) {
        $res->withText('route() works');
    });
    $response = (new ErlenClient($app))->get('/generic');
    expect($response->getBody())->toBe('route() works');
});

test('app match() registers route for specific methods only', function () {
    $app = new App();
    $app->match(['GET', 'POST'], '/multi', function (Request $req, Response $res) {
        $res->withText('match: ' . $req->getMethod());
    });
    $client = new ErlenClient($app);
    expect($client->get('/multi')->getBody())->toBe('match: GET');
    expect($client->post('/multi')->getBody())->toBe('match: POST');
    expect($client->put('/multi')->getStatusCode())->toBe(404);
});

// -------------------------------------------------------------------------
// Fallback handler
// -------------------------------------------------------------------------

test('app uses fallback handler instead of 404 when set', function () {
    $app = new App();
    $app->setFallbackHandler(function (Request $req, Response $res) {
        $res->setStatusCode(200)->withText('fallback: ' . $req->getUri());
    });
    $response = (new ErlenClient($app))->get('/anything');
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getBody())->toContain('fallback: /anything');
});

// -------------------------------------------------------------------------
// Route groups
// -------------------------------------------------------------------------

test('app group prefixes routes correctly', function () {
    $app = new App();
    $app->group('/api', function () use ($app) {
        $app->get('/', function (Request $req, Response $res) {
            $res->withText('api root');
        });
        $app->get('/users', function (Request $req, Response $res) {
            $res->withText('api users');
        });
        $app->get('/users/[id]', function (Request $req, Response $res, $params) {
            $res->withText('user ' . $params->id);
        });
    });
    $client = new ErlenClient($app);
    expect($client->get('/api')->getBody())->toBe('api root');
    expect($client->get('/api/users')->getBody())->toBe('api users');
    expect($client->get('/api/users/42')->getBody())->toBe('user 42');
    expect($client->get('/users')->getStatusCode())->toBe(404);
});

test('app group applies middlewares to all routes in group', function () {
    $app = new App();
    $app->group('/protected', function () use ($app) {
        $app->get('/data', function (Request $req, Response $res) {
            $res->withText('secret');
        });
    }, [
        function (Request $req, Response $res, $next) {
            $res->setHeader('X-Auth', 'checked');
            $next($req, $res, new stdClass());
        },
    ]);
    $response = (new ErlenClient($app))->get('/protected/data');
    expect($response->getBody())->toBe('secret')
        ->and($response->getHeaders())->toHaveKey('X-Auth', 'checked');
});

test('app nested groups compose prefixes correctly', function () {
    $app = new App();
    $app->group('/api', function () use ($app) {
        $app->group('/v2', function () use ($app) {
            $app->get('/posts', function (Request $req, Response $res) {
                $res->withText('v2 posts');
            });
        });
    });
    $client = new ErlenClient($app);
    expect($client->get('/api/v2/posts')->getBody())->toBe('v2 posts');
    expect($client->get('/api/posts')->getStatusCode())->toBe(404);
    expect($client->get('/v2/posts')->getStatusCode())->toBe(404);
});

test('app group nested middlewares run outer before inner', function () {
    $app = new App();
    $order = [];
    $app->group('/a', function () use ($app, &$order) {
        $app->group('/b', function () use ($app, &$order) {
            $app->get('/c', function (Request $req, Response $res) use (&$order) {
                $order[] = 'handler';
                $res->withText('ok');
            }, [function ($req, $res, $next) use (&$order) {
                $order[] = 'route-mw';
                $next($req, $res, new stdClass());
            }]);
        }, [function ($req, $res, $next) use (&$order) {
            $order[] = 'inner-mw';
            $next($req, $res, new stdClass());
        }]);
    }, [function ($req, $res, $next) use (&$order) {
        $order[] = 'outer-mw';
        $next($req, $res, new stdClass());
    }]);
    (new ErlenClient($app))->get('/a/b/c');
    expect($order)->toBe(['outer-mw', 'inner-mw', 'route-mw', 'handler']);
});

test('app group restores prefix after exception in callback', function () {
    $app = new App();
    try {
        $app->group('/broken', function () {
            throw new RuntimeException('setup error');
        });
    } catch (RuntimeException) {}

    $app->get('/clean', function (Request $req, Response $res) {
        $res->withText('clean');
    });
    $client = new ErlenClient($app);
    expect($client->get('/clean')->getBody())->toBe('clean');
    expect($client->get('/broken/clean')->getStatusCode())->toBe(404);
});
