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
    expect(fn() => $logger->log(LogLevel::DEBUG, 'Debug message'))
        ->not->toThrow(Exception::class);
});

test('file logger creates log directory', function () {
    $logDir = dirname(__DIR__) . '/fixtures/logs_create';
    if (is_dir($logDir)) {
        array_map('unlink', glob($logDir . '/*'));
        rmdir($logDir);
    }

    $logger = new FileLogger($logDir);
    expect(is_dir($logDir))->toBeTrue();

    if (is_dir($logDir)) {
        array_map('unlink', glob($logDir . '/*'));
        rmdir($logDir);
    }
});

test('file logger throws exception if directory cannot be created', function () {
    expect(fn() => new FileLogger('/root/logs_' . uniqid()))
        ->toThrow(RuntimeException::class);
})->skip(PHP_OS_FAMILY === 'Windows', 'Permissions work differently on Windows');

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

test('file logger rotates files when max size is exceeded', function () {
    $logDir = dirname(__DIR__) . '/fixtures/logs_rotate';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);

    array_map('unlink', glob($logDir . '/*'));

    $logger = new FileLogger($logDir);
    $logFile = $logDir . '/info.log';

    $largeContent = str_repeat('A', 3145729);
    file_put_contents($logFile, $largeContent);

    $logger->log(LogLevel::INFO, 'Trigger rotation');

    expect(file_exists($logDir . '/info.log.1'))->toBeTrue()
        ->and(filesize($logDir . '/info.log'))->toBeLessThan(1000); // O novo arquivo deve ser pequeno

    array_map('unlink', glob($logDir . '/*'));
    rmdir($logDir);
});
