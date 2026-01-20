<?php

namespace AdaiasMagdiel\Erlenmeyer\Exception;

use AdaiasMagdiel\Erlenmeyer\Logging\LoggerInterface;
use AdaiasMagdiel\Erlenmeyer\Logging\LogLevel;
use Closure;
use InvalidArgumentException;
use Throwable;

class Handler
{
    private array $handlers = [];
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function register(string $throwableClass, callable $handler): void
    {
        if (!is_a($throwableClass, Throwable::class, true)) {
            throw new InvalidArgumentException("Invalid throwable class: $throwableClass");
        }
        $this->handlers[$throwableClass] = Closure::fromCallable($handler);
        $this->logger->log(LogLevel::INFO, "Exception handler registered for class: $throwableClass");
    }

    public function getHandler(Throwable $e): ?Closure
    {
        $class = get_class($e);
        while ($class && isset($this->handlers[$class])) {
            return $this->handlers[$class];
        }

        $parent = get_parent_class($class);
        while ($parent) {
            if (isset($this->handlers[$parent])) {
                return $this->handlers[$parent];
            }
            $parent = get_parent_class($parent);
        }

        return $this->handlers[Throwable::class] ?? null;
    }
}
