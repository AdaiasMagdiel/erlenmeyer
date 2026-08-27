<?php

use AdaiasMagdiel\Erlenmeyer\App;
use AdaiasMagdiel\Erlenmeyer\Request;
use AdaiasMagdiel\Erlenmeyer\Response;

test('app run converts PHP warnings to ErrorException', function () {
    $app = new App();
    $app->get('/', function () {
        trigger_error('Test Warning', E_USER_WARNING);
    });
    $app->setExceptionHandler(ErrorException::class, function (Request $req, Response $res, Throwable $e) {
        $res->withText('Caught: ' . $e->getMessage());
    });
    ob_start();
    $app->run();
    $output = ob_get_clean();
    expect($output)->toBe('Caught: Test Warning');
});

test('app handle catches exceptions thrown during routing matching', function () {
    $app = new App();
    $app->get('/', function () {
        throw new Exception("Route Logic Fail");
    });
    $req = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);
    $res = new Response();
    $finalRes = $app->handle($req, $res);
    expect($finalRes->getStatusCode())->toBe(500)
        ->and($finalRes->getBody())->toContain('Error: Route Logic Fail');
});
