<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Exception;

/**
 * Defines a standard interface for logging messages and exceptions.
 *
 * Implementations of this interface provide consistent methods for writing
 * log entries at various levels (e.g., INFO, WARNING, ERROR) and recording
 * exception details, optionally including request context.
 */
interface LoggerInterface
{
    /**
     * Writes a log entry with the specified severity level.
     *
     * @param LogLevel $level   The severity level of the message.
     * @param string   $message The message content to log.
     * @return void
     */
    public function log(LogLevel $level, string $message): void;

    /**
     * Logs an exception, optionally including request context information.
     *
     * @param Exception    $e       The exception to log.
     * @param Request|null $request Optional request providing additional context.
     * @return void
     */
    public function logException(Exception $e, ?Request $request = null): void;
}
