<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Exception;

/**
 * Interface for logging functionality
 *
 * Provides methods for logging messages and exceptions
 */
interface LoggerInterface
{
    /**
     * Logs a message with specified level
     *
     * @param LogLevel $level   The level of the log message
     * @param string   $message The message to be logged
     *
     * @return void
     */
    public function log(LogLevel $level, string $message): void;

    /**
     * Logs an exception with optional request context
     *
     * @param Exception         $e      The exception to be logged
     * @param Request|null      $request Optional request context
     *
     * @return void
     */
    public function logException(Exception $e, ?Request $request = null): void;
}
