<?php

namespace AdaiasMagdiel\Erlenmeyer;

use InvalidArgumentException;

/**
 * Provides static methods for managing PHP session data and flash messages.
 *
 * This class simplifies interaction with the native $_SESSION superglobal.
 * It supports standard key/value storage and temporary "flash" messages,
 * which persist for a single request and are automatically cleared afterward.
 */
class Session
{
    /**
     * Ensures that a session is started before performing operations.
     *
     * This method is called internally by all other methods to make sure
     * session_start() has been invoked if necessary.
     *
     * @return void
     */
    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Retrieves a session value by key.
     *
     * @param string $key The session key to retrieve.
     * @param mixed $default The default value to return if the key does not exist.
     * @return mixed The stored session value or the default value.
     */
    public static function get(string $key, $default = null)
    {
        self::ensureSessionStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Stores a value in the session.
     *
     * @param string $key The session key to store the value under.
     * @param mixed $value The value to store.
     * @return void
     * @throws InvalidArgumentException If the key is empty.
     */
    public static function set(string $key, $value): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException("Session key cannot be empty.");
        }

        self::ensureSessionStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Checks whether a specific session key exists.
     *
     * @param string $key The session key to check.
     * @return bool True if the key exists, false otherwise.
     */
    public static function has(string $key): bool
    {
        self::ensureSessionStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * Removes a specific key and its value from the session.
     *
     * @param string $key The session key to remove.
     * @return void
     */
    public static function remove(string $key): void
    {
        self::ensureSessionStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Writes session data and ends the session.
     *
     * This releases the session file lock, allowing other requests (e.g., AJAX)
     * to proceed without waiting for the current script to terminate.
     * Use this method as soon as you are done writing to the session.
     *
     * @return void
     */
    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Sets a flash message available for the next request only.
     *
     * Flash messages are automatically removed after being retrieved.
     *
     * @param string $key The flash message key.
     * @param mixed $value The flash message value.
     * @return void
     * @throws InvalidArgumentException If the key is empty.
     */
    public static function flash(string $key, $value): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException("Flash message key cannot be empty.");
        }

        self::ensureSessionStarted();
        $_SESSION['flash'][$key] = $value;
    }

    /**
     * Retrieves and removes a flash message from the session.
     *
     * @param string $key The flash message key.
     * @param mixed $default The default value to return if not found.
     * @return mixed The flash message value or the default value.
     */
    public static function getFlash(string $key, $default = null)
    {
        self::ensureSessionStarted();

        if (isset($_SESSION['flash'][$key])) {
            $value = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);

            if (empty($_SESSION['flash'])) {
                unset($_SESSION['flash']);
            }

            return $value;
        }

        return $default;
    }

    /**
     * Checks whether a specific flash message exists.
     *
     * @param string $key The flash message key.
     * @return bool True if the flash message exists, false otherwise.
     */
    public static function hasFlash(string $key): bool
    {
        self::ensureSessionStarted();
        return isset($_SESSION['flash'][$key]);
    }
}
