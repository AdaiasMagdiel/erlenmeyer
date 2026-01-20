<?php

use AdaiasMagdiel\Erlenmeyer\Router;
use AdaiasMagdiel\Erlenmeyer\Logging\NullLogger;

beforeEach(function () {
    $this->router = new Router(new NullLogger());
});
test('router throws exception for invalid HTTP method', function () {
    expect(fn() => $this->router->add('INVALID_METHOD', '/test', fn() => null))
        ->toThrow(InvalidArgumentException::class, 'Invalid HTTP method');
});
test('router matches URI ignoring query strings manually passed', function () {
    $this->router->add('GET', '/test', fn() => 'matched');
    $match = $this->router->match('GET', '/test/');
    expect($match)->not->toBeNull()
        ->and($match['type'])->toBe('route');
});
test('router returns null for unknown method', function () {
    $this->router->add('GET', '/test', fn() => null);
    $match = $this->router->match('POST', '/test');
    expect($match)->toBeNull();
});
test('router returns null for unknown route', function () {
    $this->router->add('GET', '/test', fn() => null);
    $match = $this->router->match('GET', '/other');
    expect($match)->toBeNull();
});
test('router extracts multiple parameters', function () {
    $this->router->add('GET', '/files/[folder]/[file]', fn() => null);
    $match = $this->router->match('GET', '/files/docs/report-2024');
    expect($match)->not->toBeNull()
        ->and($match['params']->folder)->toBe('docs')
        ->and($match['params']->file)->toBe('report-2024');
});
test('router handles permanent vs temporary redirects', function () {
    $this->router->redirect('/perm', '/new-perm', true);
    $this->router->redirect('/temp', '/new-temp', false);
    $permMatch = $this->router->match('GET', '/perm');
    expect($permMatch['type'])->toBe('redirect')
        ->and($permMatch['status'])->toBe(301);
    $tempMatch = $this->router->match('GET', '/temp');
    expect($tempMatch['type'])->toBe('redirect')
        ->and($tempMatch['status'])->toBe(302);
});
test('router redirect normalizes trailing slashes on source uri', function () {
    // Registra com barra no final
    $this->router->redirect('/source/', '/target');

    // Acessa sem barra
    $match = $this->router->match('GET', '/source');

    expect($match)->not->toBeNull()
        ->and($match['type'])->toBe('redirect')
        ->and($match['to'])->toBe('/target');
});
