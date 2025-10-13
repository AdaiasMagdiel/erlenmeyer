<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Exception;

/**
 * A file-based logger implementation that writes structured log entries to disk.
 *
 * This logger automatically rotates files when they exceed a specified size (3MB)
 * and keeps a configurable number of backup logs. It supports contextual exception
 * logging, including stack traces and request metadata.
 */
class FileLogger implements LoggerInterface
{
	/**
	 * Maximum log file size in bytes (3 MB).
	 */
	private const MAX_LOG_SIZE = 3 * 1024 * 1024;

	/**
	 * Maximum number of rotated log files to retain.
	 */
	private const MAX_LOG_FILES = 5;

	/**
	 * The directory where logs are stored, or null if logging is disabled.
	 */
	private ?string $logDir;

	/**
	 * The path to the active log file.
	 */
	private string $logFile;

	/**
	 * Creates a new FileLogger instance.
	 *
	 * @param string $logDir Optional directory path for storing log files.
	 *                       If empty, logging is disabled.
	 */
	public function __construct(string $logDir = '')
	{
		$this->logDir = $logDir !== '' ? $logDir : null;

		if ($this->logDir !== null) {
			if (!is_dir($this->logDir)) {
				mkdir($this->logDir, 0755, true);
			}

			$this->logFile = rtrim($this->logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'info.log';
		}
	}

	/**
	 * Logs an exception, including request context if available.
	 *
	 * @param Exception $exception The exception to log.
	 * @param Request|null $request Optional request providing additional context.
	 * @return void
	 */
	public function logException(Exception $exception, ?Request $request = null): void
	{
		$timestamp = date('Y-m-d H:i:s');
		$requestInfo = $request
			? sprintf('Request: %s %s', $request->getMethod() ?? 'UNKNOWN', $request->getUri() ?? 'UNKNOWN')
			: 'No request context';

		$message = sprintf(
			"[%s] [ERROR] %s in %s:%d\n%s\n%s\n\n",
			$timestamp,
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			$requestInfo,
			$exception->getTraceAsString()
		);

		$this->log(LogLevel::ERROR, $message);
	}

	/**
	 * Writes a log entry to the file, rotating if necessary.
	 *
	 * @param LogLevel $level The log level (e.g., INFO, WARNING, ERROR).
	 * @param string $message The message to log.
	 * @return void
	 */
	public function log(LogLevel $level = LogLevel::INFO, string $message = ''): void
	{
		if ($this->logDir === null) {
			return; // Logging disabled
		}

		$timestamp = date('Y-m-d H:i:s');
		$logEntry = sprintf("[%s] [%s] %s\n", $timestamp, $level->value, trim($message));

		// Rotate file if needed
		if (file_exists($this->logFile) && filesize($this->logFile) >= self::MAX_LOG_SIZE) {
			$this->rotateLogFile();
		}

		file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
	}

	/**
	 * Rotates the current log file when it exceeds the maximum size.
	 *
	 * Older logs are renamed with numeric suffixes (e.g., info.log.1, info.log.2).
	 *
	 * @return void
	 */
	private function rotateLogFile(): void
	{
		// Rename existing rotated logs (e.g., info.log.1 → info.log.2)
		for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
			$oldLog = "{$this->logFile}.{$i}";
			$newLog = "{$this->logFile}." . ($i + 1);

			if (file_exists($oldLog)) {
				rename($oldLog, $newLog);
			}
		}

		// Rename current log file to .1
		if (file_exists($this->logFile)) {
			rename($this->logFile, "{$this->logFile}.1");
		}

		// Create a new, empty log file
		file_put_contents($this->logFile, '');
		$this->log(LogLevel::INFO, 'Log file rotated.');
	}
}
