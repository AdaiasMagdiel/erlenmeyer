<?php

use AdaiasMagdiel\Erlenmeyer\Session;

beforeEach(function () {
    // Start with clean session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
});

test('session sets and gets values', function () {
    Session::set('user', 'john_doe');

    expect(Session::get('user'))->toBe('john_doe')
        ->and(Session::has('user'))->toBeTrue();
});

test('session returns default for non-existent keys', function () {
    expect(Session::get('non-existent', 'default'))->toBe('default')
        ->and(Session::has('non-existent'))->toBeFalse();
});

test('session removes values', function () {
    Session::set('temp', 'value');
    Session::remove('temp');

    expect(Session::has('temp'))->toBeFalse();
});

test('session handles flash messages', function () {
    Session::flash('success', 'Operation completed');

    expect(Session::hasFlash('success'))->toBeTrue()
        ->and(Session::getFlash('success'))->toBe('Operation completed')
        ->and(Session::hasFlash('success'))->toBeFalse();
});

test('session throws exception for empty key', function () {
    expect(fn() => Session::set('', 'value'))
        ->toThrow(InvalidArgumentException::class);
});
