<?php

namespace AdaiasMagdiel\Erlenmeyer\Logging;

use AdaiasMagdiel\Erlenmeyer\Request;
use Throwable;

class NullLogger implements LoggerInterface
{
    public function log(LogLevel $level, string $message): void {}
    public function logException(Throwable $e, ?Request $request = null): void {}
};
