<?php

use AdaiasMagdiel\Erlenmeyer\Response;

beforeEach(function () {
    // Mock the header function for testing
    Response::updateFunctions(['header' => function ($header, $replace = true) {
        // Capture headers for assertion if needed
    }]);
});

test('response sets status code', function () {
    $response = new Response(201);
    expect($response->getStatusCode())->toBe(201);
});

test('response throws exception for invalid status code', function () {
    expect(fn() => new Response(999))
        ->toThrow(InvalidArgumentException::class);
});

test('response sets headers', function () {
    $response = new Response();
    $response->setHeader('X-Custom', 'value');
    expect($response->getHeaders())->toHaveKey('X-Custom', 'value');
});

test('response sets content type', function () {
    $response = new Response();
    $response->setContentType('application/json');
    expect($response->getContentType())->toBe('application/json')
        ->and($response->getHeaders())->toHaveKey('Content-Type', 'application/json');
});

test('response with JSON content', function () {
    $response = new Response();
    $data = ['name' => 'John', 'age' => 30];
    $response->withJson($data);

    expect($response->getBody())->toBeJson()
        ->and(json_decode($response->getBody(), true))->toBe($data)
        ->and($response->getContentType())->toBe('application/json; charset=UTF-8');
});

test('response with file download', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, 'file content');

    $response = new Response();
    $response->withFile($tempFile);

    expect($response->getHeaders())->toHaveKey('Content-Disposition')
        ->and($response->getBody())->toBe('file content');

    unlink($tempFile);
});

test('response detects mime type for download', function () {
    // Simulate a file path ending in .json
    $tempFile = sys_get_temp_dir() . '/test_data.json';
    file_put_contents($tempFile, '{}');

    $response = new Response();
    $response->withFile($tempFile);

    expect($response->getContentType())->toBe('application/json');
    unlink($tempFile);
});

test('response throws exception when sending twice', function () {
    $response = new Response();
    $response->withText('Test');

    ob_start();
    $response->send();
    ob_end_clean();

    expect(fn() => $response->send())->toThrow(RuntimeException::class);
});

test('response throws exception if template file does not exist', function () {
    $response = new Response();
    expect(fn() => $response->withTemplate('/invalid/path/template.php'))
        ->toThrow(RuntimeException::class, 'Template not found');
});

test('response renders template with data', function () {
    $file = sys_get_temp_dir() . '/test_template.php';
    file_put_contents($file, 'Hello <?= $name ?>');

    $response = new Response();
    $response->withTemplate($file, ['name' => 'Erlenmeyer']);

    expect($response->getBody())->toBe('Hello Erlenmeyer');
    unlink($file);
});

test('response throws exception on json encoding failure', function () {
    $response = new Response();
    // NAN causes json_encode error
    $data = ['value' => NAN];

    expect(fn() => $response->withJson($data))
        ->toThrow(RuntimeException::class, 'Failed to serialize JSON');
});

test('response throws exception for invalid redirect status code', function () {
    $response = new Response();
    expect(fn() => $response->redirect('/home', 200))
        ->toThrow(InvalidArgumentException::class);
});

test('response allows removing header', function () {
    $response = new Response();
    $response->setHeader('X-Remove-Me', 'true');
    $response->removeHeader('X-Remove-Me');

    expect($response->getHeaders())->not->toHaveKey('X-Remove-Me');
});

test('response throws exception if modifying headers after sent', function () {
    $response = new Response();
    ob_start();
    $response->send();
    ob_end_clean();

    expect(fn() => $response->setHeader('X-Late', 'true'))
        ->toThrow(RuntimeException::class);
});

test('response throws exception if setting CORS after sent', function () {
    $response = new Response();
    ob_start();
    $response->send();
    ob_end_clean();

    expect(fn() => $response->setCORS(['origin' => '*']))
        ->toThrow(RuntimeException::class);
});
