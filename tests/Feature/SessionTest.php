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
        // Should be removed after retrieval
        ->and(Session::hasFlash('success'))->toBeFalse();
});

test('session throws exception for empty key', function () {
    expect(fn() => Session::set('', 'value'))
        ->toThrow(InvalidArgumentException::class);
});

test('session regenerate works', function () {
    // Mock session start since we can't fully test session_regenerate_id in CLI without active session
    // This test primarily checks that the static method call doesn't crash the logic flow

    // We cannot easily test ID change in CLI unit test without strict process isolation
    // but we can ensure the method exists and runs.
    expect(fn() => Session::regenerate())->not->toThrow(Exception::class);
});

test('session close writes and releases session lock', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    Session::close();
    expect(session_status())->not->toBe(PHP_SESSION_ACTIVE);
});

test('session close does nothing when session not active', function () {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    expect(fn() => Session::close())->not->toThrow(Exception::class);
});

test('session has returns true for existing key', function () {
    Session::set('has_test_key', 'yes');
    expect(Session::has('has_test_key'))->toBeTrue();
    Session::remove('has_test_key');
});

test('session has returns false for missing key', function () {
    Session::remove('missing_has_key');
    expect(Session::has('missing_has_key'))->toBeFalse();
});

test('session flash throws for empty key', function () {
    expect(fn() => Session::flash('', 'value'))
        ->toThrow(InvalidArgumentException::class);
});

test('session getFlash returns default for missing key', function () {
    $value = Session::getFlash('nonexistent_flash_xyz', 'default_val');
    expect($value)->toBe('default_val');
});

test('session hasFlash returns false for non-set flash', function () {
    expect(Session::hasFlash('nonexistent_flash_abc'))->toBeFalse();
});

test('session hasFlash returns true for set flash', function () {
    Session::flash('hf_key', 'msg');
    expect(Session::hasFlash('hf_key'))->toBeTrue();
    Session::getFlash('hf_key');
});
