<?php
// tests/Unit/ErlenmeyerTest.php

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Assets;
use AdaiasMagdiel\Erlenmeyer\Testing\ErlenClient;
use AdaiasMagdiel\Erlenmeyer\Logging\ConsoleLogger;
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;

test('full application flow with routing and middleware', function () {
    $logger = new ConsoleLogger([LogLevel::INFO]);
    $app = new App(null, $logger);

    // Add global middleware
    $app->addMiddleware(function ($req, $res, $next) {
        $res->setHeader('X-Global-Middleware', 'applied');
        $next($req, $res, new stdClass());
    });

    // Add routes with route-specific middleware
    $app->get('/users', function ($req, $res) {
        $res->withJson(['users' => ['john', 'jane']]);
    });

    $app->post('/users', function ($req, $res) {
        $data = $req->getJson();
        $res->setStatusCode(201)->withJson(['created' => $data['name']]);
    });

    $client = new ErlenClient($app);

    // Test GET request
    $getResponse = $client->get('/users');
    expect($getResponse->getStatusCode())->toBe(200)
        ->and($getResponse->getHeaders())->toHaveKey('X-Global-Middleware', 'applied')
        ->and($getResponse->getBody())->toBeJson();

    // Test POST request with JSON
    $postResponse = $client->post('/users', [
        'json' => ['name' => 'newuser'],
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ]);

    expect($postResponse->getStatusCode())->toBe(201)
        ->and(json_decode($postResponse->getBody(), true))->toHaveKey('created', 'newuser');
});

test('application handles errors and exceptions gracefully', function () {
    $logger = new ConsoleLogger([LogLevel::INFO]);
    $app = new App(null, $logger);

    $app->get('/error', function () {
        throw new RuntimeException('Something went wrong');
    });

    $app->setExceptionHandler(RuntimeException::class, function ($req, $res, $e) {
        $res->setStatusCode(500)->withJson(['error' => $e->getMessage()]);
    });

    $client = new ErlenClient($app);
    $response = $client->get('/error');

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toHaveKey('error', 'Something went wrong');
});

test('application with assets integration', function () {
    $testDir = dirname(__DIR__) . '/fixtures/public';
    if (!is_dir($testDir)) {
        mkdir($testDir, 0755, true);
    }
    file_put_contents($testDir . '/style.css', 'body { color: red; }');

    $assets = new Assets($testDir, 'static');
    $logger = new ConsoleLogger([LogLevel::INFO]);
    $app = new App($assets, $logger);

    $client = new ErlenClient($app);

    // Test that normal routes still work
    $app->get('/api/data', function ($req, $res) {
        $res->withJson(['data' => 'test']);
    });

    $apiResponse = $client->get('/api/data');
    expect($apiResponse->getStatusCode())->toBe(200);

    // Cleanup
    unlink($testDir . '/style.css');
    rmdir($testDir);
});
