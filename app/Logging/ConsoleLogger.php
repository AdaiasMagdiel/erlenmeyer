<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Logger that writes to the console/error_log with support for log levels and exclusions.
 */
class ConsoleLogger implements LoggerInterface
{
	/** The format used for log timestamps. */
	private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

	/** 
	 * @var LogLevel[] List of excluded log levels. 
	 */
	private array $excludedLogLevels = [];

	/**
	 * Initializes the console logger.
	 *
	 * @param LogLevel[] $excludedLogLevels List of log levels to ignore.
	 * @throws InvalidArgumentException If any item in the array is not a valid LogLevel instance.
	 */
	public function __construct(array $excludedLogLevels = [])
	{
		$this->validateExcludedLogLevels($excludedLogLevels);
		$this->excludedLogLevels = $excludedLogLevels;
	}

	/**
	 * Logs an exception with optional request context.
	 *
	 * @param Throwable    $exception The exception to log.
	 * @param Request|null $request   The request context (optional).
	 */
	public function logException(Throwable $exception, ?Request $request = null): void
	{
		$message = $this->formatExceptionMessage($exception, $request);
		$this->log(LogLevel::ERROR, $message);
	}

	/**
	 * Logs a message at a specific level.
	 *
	 * @param LogLevel $level   The severity level of the log.
	 * @param string   $message The message to log.
	 * 
	 * @throws InvalidArgumentException If the log message is empty.
	 */
	public function log(LogLevel $level = LogLevel::INFO, string $message = ''): void
	{
		$message = trim($message);
		if ($message === '') {
			throw new InvalidArgumentException('Log message cannot be empty.');
		}

		if ($this->shouldSkipLevel($level)) {
			return;
		}

		$logEntry = sprintf(
			"[%s] [%s] %s\n",
			date(self::TIMESTAMP_FORMAT),
			$level->value,
			$message
		);

		if (!@error_log($logEntry)) {
			// Robust fallback
			@fwrite(STDERR, "Failed to write log entry:\n" . $logEntry);
		}
	}

	/**
	 * Checks if the specified log level should be skipped.
	 *
	 * @param LogLevel $level The level to check.
	 * @return bool True if the level is excluded, false otherwise.
	 */
	private function shouldSkipLevel(LogLevel $level): bool
	{
		return in_array($level, $this->excludedLogLevels, true);
	}

	/**
	 * Validates the array of excluded log levels.
	 *
	 * @param array $levels The array of levels to validate.
	 * @throws InvalidArgumentException If an invalid level instance is found.
	 */
	private function validateExcludedLogLevels(array $levels): void
	{
		foreach ($levels as $level) {
			if (!$level instanceof LogLevel) {
				throw new InvalidArgumentException(
					'All excluded log levels must be instances of LogLevel.'
				);
			}
		}
	}

	/**
	 * Formats the exception message, including request info and stack trace.
	 *
	 * @param Throwable    $e   The exception.
	 * @param Request|null $req The request context.
	 * @return string The formatted log string.
	 */
	private function formatExceptionMessage(Throwable $e, ?Request $req): string
	{
		$requestInfo = $req
			? sprintf('Request: %s %s', $req->getMethod() ?? 'UNKNOWN', $req->getUri() ?? 'UNKNOWN')
			: 'No request context';

		return sprintf(
			"%s in %s:%d\n%s\n%s",
			htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
			$e->getFile(),
			$e->getLine(),
			$requestInfo,
			$this->formatStackTrace($e->getTraceAsString())
		);
	}

	/**
	 * Formats the stack trace for better readability.
	 *
	 * @param string $trace The raw stack trace string.
	 * @return string The formatted stack trace.
	 */
	private function formatStackTrace(string $trace): string
	{
		$lines = array_filter(array_map('trim', explode("\n", $trace)));
		return "Stack trace:\n  " . implode("\n  ", $lines);
	}
}
