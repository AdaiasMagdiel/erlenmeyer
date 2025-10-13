<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

/**
 * Enumeration of log severity levels.
 *
 * Defines standard logging levels used across the application to categorize
 * messages by importance and severity.
 */
enum LogLevel: string
{
	case DEBUG    = 'DEBUG';
	case INFO     = 'INFO';
	case WARNING  = 'WARNING';
	case ERROR    = 'ERROR';
	case CRITICAL = 'CRITICAL';
}
