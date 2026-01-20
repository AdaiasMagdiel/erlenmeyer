<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Throwable;

/**
 * A "Null Object" implementation of the LoggerInterface.
 *
 * This logger acts as a black hole, silently discarding all log messages
 * and exceptions. It is useful for testing environments or when logging
 * needs to be completely disabled.
 */
class NullLogger implements LoggerInterface
{
    /**
     * Discards the log message.
     * 
     * Intentionally performs no action.
     *
     * @param LogLevel $level   The severity level (ignored).
     * @param string   $message The message content (ignored).
     */
    public function log(LogLevel $level, string $message): void {}

    /**
     * Discards the exception details.
     * 
     * Intentionally performs no action.
     *
     * @param Throwable    $e       The exception (ignored).
     * @param Request|null $request The request context (ignored).
     */
    public function logException(Throwable $e, ?Request $request = null): void {}
}
