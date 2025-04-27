<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

/**
 * Logging level enumeration
 *
 * Defines different levels of logging severity for categorizing messages.
 *
 * @package AdaiasMagdiel\Erlenmeyer\Logging
 */
enum LogLevel: string
{
	case INFO     = 'INFO';
	case DEBUG    = 'DEBUG';
	case WARNING  = 'WARNING';
	case ERROR    = 'ERROR';
	case CRITICAL = 'CRITICAL';
}
