<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Exception;

class FileLogger implements LoggerInterface
{
	/**
	 * Maximum log file size in bytes (3MB)
	 */
	private const MAX_LOG_SIZE = 3145728; // 3MB

	/**
	 * Maximum number of rotated log files to keep
	 */
	private const MAX_LOG_FILES = 5;

	/**
	 * Path to the log directory
	 */
	private ?string $logDir;

	/**
	 * Current log file path
	 */
	private string $logFile;

	/**
	 * Constructs a new DefaultLogger instance.
	 *
	 * @param string $logDir [Optional] Path to the log directory. If empty, disables logging.
	 */
	public function __construct(string $logDir = "")
	{
		$this->logDir = empty($logDir) ? null : $logDir;

		if (!is_null($this->logDir) && !empty($this->logDir)) {
			// Ensure logs directory exists
			if (!is_dir($this->logDir)) {
				mkdir($this->logDir, 0755, true);
			}
			$this->logFile = $this->logDir . "/info.log";
		}
	}

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
	 * Logs a message to the log file, ensuring the file size does not exceed 3MB.
	 *
	 * @param LogLevel $level Log level (e.g., INFO, ERROR, WARNING).
	 * @param string $message Message to log.
	 * @return void
	 */
	public function log(LogLevel $level = LogLevel::INFO, string $message = ""): void
	{
		if (is_null($this->logDir)) return;

		// Format log entry with timestamp and level
		$timestamp = date('Y-m-d H:i:s');
		$logEntry = "[{$timestamp}] [{$level->value}] $message\n";

		// Check if log file exists and its size
		if (file_exists($this->logFile) && filesize($this->logFile) >= self::MAX_LOG_SIZE) {
			// Rotate log file
			$this->rotateLogFile();
		}

		// Write to log file
		file_put_contents($this->logFile, $logEntry, FILE_APPEND);
	}

	/**
	 * Rotates the log file if it exceeds the maximum size.
	 *
	 * @return void
	 */
	private function rotateLogFile(): void
	{
		// Determine the next available log file number
		for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
			$oldLog = $this->logFile . '.' . $i;
			$newLog = $this->logFile . '.' . ($i + 1);
			if (file_exists($oldLog)) {
				rename($oldLog, $newLog);
			}
		}

		// Rename current log file to .1
		if (file_exists($this->logFile)) {
			rename($this->logFile, $this->logFile . '.1');
		}

		// Create a new empty log file
		file_put_contents($this->logFile, '');
		$this->log(LogLevel::INFO, 'Log file rotated', false);
	}
}
