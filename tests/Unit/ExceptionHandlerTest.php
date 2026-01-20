<?php

use AdaiasMagdiel\Erlenmeyer\Exception\Handler;
use AdaiasMagdiel\Erlenmeyer\Logging\NullLogger;

beforeEach(function () {
    $this->handler = new Handler(new NullLogger());
});

test('handler registers and retrieves exact match', function () {
    $action = fn() => 'handled';
    $this->handler->register(RuntimeException::class, $action);

    $retrieved = $this->handler->getHandler(new RuntimeException());

    expect($retrieved)->toBe($action);
});

test('handler throws exception when registering invalid class', function () {
    expect(fn() => $this->handler->register(stdClass::class, fn() => null))
        ->toThrow(InvalidArgumentException::class);
});

test('handler resolves parent class handler for child exception', function () {
    // Registra handler para Exception (Pai)
    $action = fn() => 'parent handler';
    $this->handler->register(Exception::class, $action);

    // Busca handler para LogicException (Filho de Exception)
    $retrieved = $this->handler->getHandler(new LogicException('logic error'));

    expect($retrieved)->toBe($action);
});

test('handler returns null if no match found in hierarchy', function () {
    $this->handler->register(RuntimeException::class, fn() => null);

    // LogicException não herda de RuntimeException
    $retrieved = $this->handler->getHandler(new LogicException());

    expect($retrieved)->toBeNull();
});
