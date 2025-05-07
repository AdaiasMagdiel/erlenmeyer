<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Exception;

class ConsoleLogger implements LoggerInterface
{
	/**
	 * Logs an exception with request context.
	 *
	 * @param Exception $e     The exception to log
	 * @param Request  $request [Optional] The request object
	 * @return void
	 */
	public function logException(Exception $e, ?Request $request = null): void
	{
		// Format error message with timestamp, request details, and stack trace
		$timestamp = date('Y-m-d H:i:s');
		$requestInfo = $request ? "Request: {$request->getMethod()} {$request->getUri()}" : 'No request context';
		$message = "[{$timestamp}] [ERROR] {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n";
		$message .= "$requestInfo\n";
		$message .= $e->getTraceAsString() . "\n\n";

		// Write to log file with size check
		$this->log(LogLevel::ERROR, $message);
	}

	/**
	 * Logs a message to the console.
	 *
	 * @param LogLevel $level Log level (e.g., INFO, ERROR, WARNING).
	 * @param string $message Message to log.
	 * @return void
	 */
	public function log(LogLevel $level = LogLevel::INFO, string $message = ""): void
	{
		// Format log entry with timestamp and level
		$timestamp = date('Y-m-d H:i:s');
		$logEntry = "[{$timestamp}] [{$level->value}] $message\n";

		// Write or append to log file
		error_log($logEntry);
	}
}
