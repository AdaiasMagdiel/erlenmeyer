<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Exception;

class ConsoleLogger implements LoggerInterface
{
	private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Initializes a new ConsoleLogger instance with optional log levels to exclude.
	 *
	 * @param LogLevel[] $excludedLogLevels An array of LogLevel enums to exclude from logging. Defaults to an empty array.
	 * @throws \InvalidArgumentException If any element in $excludedLogLevels is not a LogLevel instance.
	 */
	public function __construct(public array $excludedLogLevels = [])
	{
		foreach ($excludedLogLevels as $level) {
			if (!$level instanceof LogLevel) {
				throw new \InvalidArgumentException('All excluded log levels must be instances of LogLevel.');
			}
		}
	}

	/**
	 * Logs an exception with request context.
	 *
	 * @param Exception $exception The exception to log.
	 * @param Request|null $request The request object providing context, if available.
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
	 * Logs a message to the console.
	 *
	 * @param LogLevel $level The log level (e.g., INFO, ERROR, WARNING). Defaults to INFO.
	 * @param string $message The message to log. Must not be empty.
	 * @throws \InvalidArgumentException If the message is empty.
	 */
	public function log(LogLevel $level = LogLevel::INFO, string $message = ''): void
	{
		if (empty($message)) {
			throw new \InvalidArgumentException('Log message cannot be empty.');
		}

		if (in_array($level, $this->excludedLogLevels, true)) {
			return;
		}

		$timestamp = date(self::TIMESTAMP_FORMAT);
		$logEntry = sprintf("[%s] [%s] %s\n", $timestamp, $level->value, $message);

		if (!error_log($logEntry)) {
			// Fallback or additional handling could be added here
			fprintf(STDERR, "Failed to write log: %s", $logEntry);
		}
	}

	/**
	 * Formats a stack trace for better readability.
	 *
	 * @param string $stackTrace The raw stack trace string.
	 * @return string The formatted stack trace.
	 */
	private function formatStackTrace(string $stackTrace): string
	{
		return implode("\n  ", explode("\n", trim($stackTrace)));
	}
}
