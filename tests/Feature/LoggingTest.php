<?php

use AdaiasMagdiel\Erlenmeyer\Logging\ConsoleLogger;
use AdaiasMagdiel\Erlenmeyer\Logging\FileLogger;
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;
use AdaiasMagdiel\Erlenmeyer\Request;

test('console logger logs messages', function () {
    $logger = new ConsoleLogger();

    expect(fn() => $logger->log(LogLevel::INFO, 'Test message'))
        ->not->toThrow(Exception::class);
});

test('console logger excludes specified levels', function () {
    $logger = new ConsoleLogger([LogLevel::DEBUG]);

    // This should not produce any output or error
    expect(fn() => $logger->log(LogLevel::DEBUG, 'Debug message'))
        ->not->toThrow(Exception::class);
});

test('file logger creates log directory', function () {
    $logDir = dirname(__DIR__) . '/fixtures/logs';
    $logger = new FileLogger($logDir);

    expect(is_dir($logDir))->toBeTrue();

    // Cleanup
    if (is_dir($logDir)) {
        array_map('unlink', glob($logDir . '/*'));
        rmdir($logDir);
    }
});

test('logger throws exception for empty message', function () {
    $logger = new ConsoleLogger();

    expect(fn() => $logger->log(LogLevel::INFO, ''))
        ->toThrow(InvalidArgumentException::class);
});

test('logger handles exceptions', function () {
    $logger = new ConsoleLogger();
    $exception = new RuntimeException('Test exception');
    $request = new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test']);

    expect(fn() => $logger->logException($exception, $request))
        ->not->toThrow(Exception::class);
});
