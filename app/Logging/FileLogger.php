<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Throwable;

/**
 * File-based logger with automatic log rotation.
 *
 * Writes timestamped, structured log lines to disk and rotates the main log
 * file when exceeding a configured size (3 MB). Retains up to 5 backups.
 */
class FileLogger implements LoggerInterface
{
	/** @var int Maximum log file size in bytes (3 MB). */
	private const MAX_LOG_SIZE = 3 * 1024 * 1024;

	/** @var int Maximum number of rotated files to keep. */
	private const MAX_LOG_FILES = 5;

	/** @var string|null Directory where logs are stored. Null disables logging. */
	private ?string $logDir;

	/** @var string Path to the active log file. */
	private string $logFile;

	/**
	 * @param string $logDir Directory where logs will be stored.
	 *                       Empty string disables logging completely.
	 */
	public function __construct(string $logDir = '')
	{
		$this->logDir = $logDir !== '' ? rtrim($logDir, DIRECTORY_SEPARATOR) : null;

		if ($this->logDir !== null) {
			if (!is_dir($this->logDir)) {
				@mkdir($this->logDir, 0755, true);
			}

			$this->logFile = $this->logDir . DIRECTORY_SEPARATOR . 'info.log';
		}
	}

	/**
	 * Logs an exception with optional request info.
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
	 * Writes a log entry and rotates the file when needed.
	 */
	public function log(LogLevel $level = LogLevel::INFO, string $message = ''): void
	{
		if ($this->logDir === null) {
			return; // Logging disabled
		}

		// Normalize multi-line logs nicely
		$message = trim($message);
		if ($message === '') {
			return;
		}

		$entry = sprintf(
			"[%s] [%s] %s\n",
			date('Y-m-d H:i:s'),
			$level->value,
			$message
		);

		$this->rotateIfNeeded();

		@file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Rotate the active log file if it exceeds MAX_LOG_SIZE.
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
	 * Perform log rotation:
	 *   info.log      → info.log.1
	 *   info.log.1    → info.log.2
	 *   ...
	 *   info.log.5    → deleted
	 */
	private function rotateLogFile(): void
	{
		// Delete oldest file if beyond retention
		$oldest = "{$this->logFile}." . self::MAX_LOG_FILES;
		if (file_exists($oldest)) {
			@unlink($oldest);
		}

		// Shift rotated files: .4 → .5, .3 → .4, etc.
		for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
			$source = "{$this->logFile}.{$i}";
			$target = "{$this->logFile}." . ($i + 1);

			if (file_exists($source)) {
				@rename($source, $target);
			}
		}

		// Move main log to .1
		if (file_exists($this->logFile)) {
			@rename($this->logFile, "{$this->logFile}.1");
		}

		// Touch a fresh file
		@file_put_contents($this->logFile, '');

		// Log that rotation occurred
		$this->log(LogLevel::INFO, 'Log file rotated.');
	}
}
