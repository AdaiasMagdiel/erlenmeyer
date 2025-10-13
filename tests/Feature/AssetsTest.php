<?php

use AdaiasMagdiel\Erlenmeyer\Assets;
use AdaiasMagdiel\Erlenmeyer\Request;

beforeEach(function () {
    // Create test assets directory
    $this->testDir = dirname(__DIR__) . '/fixtures/public';
    if (!is_dir($this->testDir)) {
        mkdir($this->testDir, 0755, true);
    }

    // Create test files
    file_put_contents($this->testDir . '/test.txt', 'Hello World');
    file_put_contents($this->testDir . '/image.jpg', 'fake image data');
});

afterEach(function () {
    // Cleanup test files
    if (is_dir($this->testDir)) {
        array_map('unlink', glob($this->testDir . '/*'));
        rmdir($this->testDir);
    }
});

test('assets can be instantiated with valid directory', function () {
    $assets = new Assets($this->testDir);

    expect($assets)->toBeInstanceOf(Assets::class);
});

test('assets throws exception for invalid directory', function () {
    expect(fn() => new Assets('/invalid/path'))
        ->toThrow(InvalidArgumentException::class);
});

test('assets detects asset requests', function () {
    $_SERVER['REQUEST_URI'] = '/assets/test.txt';
    $assets = new Assets($this->testDir, 'assets');

    $req = new Request($_SERVER);

    expect($assets->isAssetRequest($req))->toBeTrue();
});

test('assets serves existing files', function () {
    $_SERVER['REQUEST_URI'] = '/assets/test.txt';
    $assets = new Assets($this->testDir, 'assets');

    $req = new Request($_SERVER);

    ob_start();
    $result = $assets->serveAsset($req);
    $output = ob_get_clean();

    expect($result)->toBeTrue()
        ->and($output)->toBe('Hello World');
});

test('assets returns false for non-existent files', function () {
    $_SERVER['REQUEST_URI'] = '/assets/non-existent.txt';
    $assets = new Assets($this->testDir, 'assets');

    $req = new Request($_SERVER);

    ob_start();
    $result = $assets->serveAsset($req);
    $output = ob_get_clean();

    expect($result)->toBeFalse()
        ->and($output)->toContain('File not found');
});

test('assets detects correct MIME types', function () {
    expect(Assets::detectMimeType('test.css'))->toBe('text/css')
        ->and(Assets::detectMimeType('image.png'))->toBe('image/png')
        ->and(Assets::detectMimeType('data.json'))->toBe('application/json')
        ->and(Assets::detectMimeType('unknown.xyz'))->toBe('application/octet-stream');
});

test('assets prevents directory traversal', function () {
    $_SERVER['REQUEST_URI'] = '/assets/../secrets.txt';
    $assets = new Assets($this->testDir, 'assets');

    $req = new Request($_SERVER);

    ob_start();
    $result = $assets->serveAsset($req);
    $output = ob_get_clean();

    expect($result)->toBeFalse()
        ->and($output)->toContain('File not found');
});

test('assets handles empty request URI', function () {
    $_SERVER['REQUEST_URI'] = '';
    $assets = new Assets($this->testDir, 'assets');

    $req = new Request($_SERVER);

    ob_start();
    $result = $assets->serveAsset($req);
    $output = ob_get_clean();

    expect($result)->toBeFalse()
        ->and($output)->toContain('File not found');
});
