<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Logger que escreve no console/error_log com suporte a níveis e exclusões.
 */
class ConsoleLogger implements LoggerInterface
{
	/** Timestamp format. */
	private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

	/** @var LogLevel[] Lista de níveis excluídos. */
	private array $excludedLogLevels = [];

	/**
	 * @param LogLevel[] $excludedLogLevels Lista de níveis a ignorar.
	 */
	public function __construct(array $excludedLogLevels = [])
	{
		$this->validateExcludedLogLevels($excludedLogLevels);
		$this->excludedLogLevels = $excludedLogLevels;
	}

	/**
	 * Registra exceções com contexto opcional da request.
	 */
	public function logException(Throwable $exception, ?Request $request = null): void
	{
		$message = $this->formatExceptionMessage($exception, $request);
		$this->log(LogLevel::ERROR, $message);
	}

	/**
	 * Registra uma mensagem de log.
	 *
	 * @throws InvalidArgumentException
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
			// Fallback robusto
			@fwrite(STDERR, "Failed to write log entry:\n" . $logEntry);
		}
	}

	/**
	 * Verifica se o nível deve ser ignorado.
	 */
	private function shouldSkipLevel(LogLevel $level): bool
	{
		return in_array($level, $this->excludedLogLevels, true);
	}

	/**
	 * Valida o array de níveis excluídos.
	 *
	 * @throws InvalidArgumentException
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
	 * Monta mensagem formatada para exceção.
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
	 * Formata stack trace para melhor leitura.
	 */
	private function formatStackTrace(string $trace): string
	{
		$lines = array_filter(array_map('trim', explode("\n", $trace)));
		return "Stack trace:\n  " . implode("\n  ", $lines);
	}
}
