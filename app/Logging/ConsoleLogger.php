<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Exception;

/**
 * A console-based logger implementation that writes log entries to the PHP error log.
 *
 * This logger supports different log levels and can be configured to exclude specific
 * levels. It also provides contextual logging for exceptions, including request details
 * and stack traces.
 */
class ConsoleLogger implements LoggerInterface
{
	/**
	 * Timestamp format used for all log entries.
	 */
	private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

	/**
	 * @var LogLevel[] Log levels that should be excluded from logging.
	 */
	public array $excludedLogLevels = [];

	/**
	 * Creates a new ConsoleLogger instance.
	 *
	 * @param LogLevel[] $excludedLogLevels An array of LogLevel enums to exclude from logging.
	 * @throws \InvalidArgumentException If any element in $excludedLogLevels is not a LogLevel instance.
	 */
	public function __construct(array $excludedLogLevels = [])
	{
		foreach ($excludedLogLevels as $level) {
			if (!$level instanceof LogLevel) {
				throw new \InvalidArgumentException('All excluded log levels must be instances of LogLevel.');
			}
		}

		$this->excludedLogLevels = $excludedLogLevels;
	}

	/**
	 * Logs an exception, including context about the originating request if available.
	 *
	 * @param Exception $exception The exception to log.
	 * @param Request|null $request Optional request object providing additional context.
	 * @return void
	 */
	public function logException(Exception $exception, ?Request $request = null): void
	{
		$timestamp = date(self::TIMESTAMP_FORMAT);

		$requestInfo = $request
			? sprintf('Request: %s %s', $request->getMethod() ?? 'UNKNOWN', $request->getUri() ?? 'UNKNOWN')
			: 'No request context';

		// Escape message to prevent log injection
		$message = sprintf(
			"[%s] [ERROR] %s in %s:%d\n%s\n%s\n\n",
			$timestamp,
			htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
			$exception->getFile(),
			$exception->getLine(),
			$requestInfo,
			$this->formatStackTrace($exception->getTraceAsString())
		);

		$this->log(LogLevel::ERROR, $message);
	}

	/**
	 * Logs a message to the PHP error log or STDERR.
	 *
	 * @param LogLevel $level The log level (e.g., INFO, WARNING, ERROR).
	 * @param string $message The message to log. Must not be empty.
	 * @return void
	 * @throws \InvalidArgumentException If the message is empty.
	 */
	public function log(LogLevel $level = LogLevel::INFO, string $message = ''): void
	{
		if (trim($message) === '') {
			throw new \InvalidArgumentException('Log message cannot be empty.');
		}

		if (in_array($level, $this->excludedLogLevels, true)) {
			return;
		}

		$timestamp = date(self::TIMESTAMP_FORMAT);
		$logEntry = sprintf("[%s] [%s] %s\n", $timestamp, $level->value, $message);

		// Attempt to log; fallback to STDERR if error_log() fails
		if (!@error_log($logEntry)) {
			fprintf(STDERR, "Failed to write log: %s", $logEntry);
		}
	}

	/**
	 * Formats a stack trace for improved readability.
	 *
	 * @param string $stackTrace The raw stack trace.
	 * @return string The formatted stack trace.
	 */
	private function formatStackTrace(string $stackTrace): string
	{
		return implode("\n  ", explode("\n", trim($stackTrace)));
	}
}
