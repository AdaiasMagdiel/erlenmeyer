<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use RuntimeException;
use Throwable;

/**
 * A file-based logging implementation supporting automatic rotation and backup retention.
 *
 * This logger writes timestamped, structured log lines to disk. It automatically
 * rotates the main log file when it exceeds the configured size limit (3 MB)
 * and retains a configurable number of backup files.
 * 
 * It ensures thread-safe writes using file locks and handles filesystem errors 
 * gracefully by falling back to PHP's native error_log.
 */
class FileLogger implements LoggerInterface
{
	/** 
	 * Maximum log file size in bytes before rotation occurs (3 MB).
	 */
	private const MAX_LOG_SIZE = 3 * 1024 * 1024;

	/** 
	 * Maximum number of rotated backup files to retain.
	 */
	private const MAX_LOG_FILES = 5;

	/** 
	 * Directory where logs are stored. If null, logging is disabled.
	 * 
	 * @var string|null
	 */
	private ?string $logDir;

	/** 
	 * Full path to the currently active log file.
	 * 
	 * @var string
	 */
	private string $logFile;

	/**
	 * Initializes the file logger.
	 * 
	 * Creates the log directory if it does not exist and validates write permissions.
	 *
	 * @param string $logDir The directory path where logs will be stored.
	 *                       Passing an empty string disables logging completely.
	 * 
	 * @throws RuntimeException If the directory cannot be created or is not writable.
	 */
	public function __construct(string $logDir = '')
	{
		$this->logDir = $logDir !== '' ? rtrim($logDir, DIRECTORY_SEPARATOR) : null;

		if ($this->logDir !== null) {
			if (!is_dir($this->logDir)) {
				// Attempt to create the directory recursively
				if (!mkdir($this->logDir, 0755, true) && !is_dir($this->logDir)) {
					throw new RuntimeException(sprintf('Directory "%s" was not created', $this->logDir));
				}
			}
			$this->logFile = $this->logDir . DIRECTORY_SEPARATOR . 'info.log';

			// Ensure the file is writable if it already exists
			if (file_exists($this->logFile) && !is_writable($this->logFile)) {
				throw new RuntimeException(sprintf('Log file "%s" is not writable', $this->logFile));
			}
		}
	}

	/**
	 * Logs a captured exception with detailed context.
	 * 
	 * Includes the exception message, file, line number, stack trace, and 
	 * optional HTTP request details (method and URI) to aid debugging.
	 *
	 * @param Throwable    $exception The exception to log.
	 * @param Request|null $request   Optional request object to add context.
	 */
	public function logException(Throwable $exception, ?Request $request = null): void
	{
		$requestInfo = $request
			? sprintf(
				'Request: %s %s',
				$request->getMethod() ?? 'UNKNOWN',
				$request->getUri() ?? 'unknown-uri'
			)
			: 'No request context';

		$message = sprintf(
			"%s in %s:%d\n%s\n%s",
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			$requestInfo,
			$exception->getTraceAsString()
		);

		$this->log(LogLevel::ERROR, $message);
	}

	/**
	 * Writes a formatted log entry to the active log file.
	 * 
	 * Handles log rotation if the file size limit is reached. If writing to the
	 * file fails (e.g., disk full, permission lost), the error is reported to 
	 * PHP's native error_log to prevent silent failures.
	 *
	 * @param LogLevel $level   The severity level of the log.
	 * @param string   $message The message content to log.
	 */
	public function log(LogLevel $level = LogLevel::INFO, string $message = ''): void
	{
		if ($this->logDir === null) {
			return;
		}

		$message = trim($message);
		if ($message === '') {
			return;
		}

		$entry = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level->value, $message);

		try {
			$this->rotateIfNeeded();

			// Attempt to write with an exclusive lock
			if (file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX) === false) {
				error_log("FileLogger Error: Could not write to {$this->logFile}");
			}
		} catch (Throwable $e) {
			// Fallback logging for critical logger failures
			error_log("FileLogger Critical Failure: " . $e->getMessage());
		}
	}

	/**
	 * Checks the current log file size and triggers rotation if limits are exceeded.
	 */
	private function rotateIfNeeded(): void
	{
		if (!file_exists($this->logFile)) {
			return;
		}

		if (filesize($this->logFile) < self::MAX_LOG_SIZE) {
			return;
		}

		$this->rotateLogFile();
	}

	/**
	 * Rotates the log files to preserve history.
	 * 
	 * Rotation Logic:
	 *   info.log      -> info.log.1
	 *   info.log.1    -> info.log.2
	 *   ...
	 *   info.log.5    -> deleted
	 * 
	 * A new empty info.log is created after rotation.
	 */
	private function rotateLogFile(): void
	{
		// Delete the oldest backup file if it exceeds retention limit
		$oldest = "{$this->logFile}." . self::MAX_LOG_FILES;
		if (file_exists($oldest)) {
			@unlink($oldest);
		}

		// Shift existing rotated files: .4 -> .5, .3 -> .4, etc.
		for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
			$source = "{$this->logFile}.{$i}";
			$target = "{$this->logFile}." . ($i + 1);

			if (file_exists($source)) {
				@rename($source, $target);
			}
		}

		// Rename the current main log to .1 (most recent backup)
		if (file_exists($this->logFile)) {
			@rename($this->logFile, "{$this->logFile}.1");
		}

		// Create a fresh log file
		@file_put_contents($this->logFile, '');

		// Record the rotation event in the new file
		$this->log(LogLevel::INFO, 'Log file rotated.');
	}
}
