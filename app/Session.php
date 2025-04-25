<?php

namespace AdaiasMagdiel\Erlenmeyer;

/**
 * Session management class with static methods for handling session data and flash messages.
 *
 * Provides a simple interface to interact with PHP's $_SESSION superglobal, including
 * support for flash messages that are automatically cleared after retrieval.
 */
class Session
{
    /**
     * Initializes the session if not already started.
     *
     * Called internally to ensure session is active before operations.
     */
    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Retrieves a value from the session by key.
     *
     * @param string $key The session key to retrieve.
     * @param mixed $default Default value to return if key does not exist.
     * @return mixed The session value or the default value.
     */
    public static function get(string $key, $default = null)
    {
        self::ensureSessionStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Sets a value in the session.
     *
     * @param string $key The March to store the value.
     * @param mixed $value The value to store.
     * @return void
     * @throws \InvalidArgumentException If the key is empty.
     */
    public static function set(string $key, $value): void
    {
        if (empty($key)) {
            throw new \InvalidArgumentException("Session key cannot be empty.");
        }
        self::ensureSessionStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Checks if a session key exists.
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
     * Removes a session key and its value.
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
     * Sets a flash message that will be available for the next request only.
     *
     * @param string $key The flash message key.
     * @param mixed $value The flash message value.
     * @return void
     * @throws \InvalidArgumentException If the key is empty.
     */
    public static function flash(string $key, $value): void
    {
        if (empty($key)) {
            throw new \InvalidArgumentException("Flash message key cannot be empty.");
        }
        self::ensureSessionStarted();
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash'][$key] = $value;
    }

    /**
     * Retrieves a flash message and removes it from the session.
     *
     * @param string $key The flash message key.
     * @param mixed $default Default value to return if key does not exist.
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
     * Checks if a flash message exists.
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
