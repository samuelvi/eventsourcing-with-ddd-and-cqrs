<?php

declare(strict_types=1);

namespace App\Application\Logger;

use Psr\Log\LoggerInterface;

final class AppLogger
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function info(string $message, mixed $context = []): void
    {
        $this->logger->info($message, (array) $context);
    }

    public function error(string $message, mixed $context = []): void
    {
        $this->logger->error($message, (array) $context);
    }

    public function warning(string $message, mixed $context = []): void
    {
        $this->logger->warning($message, (array) $context);
    }

    public function debug(string $message, mixed $context = []): void
    {
        $this->logger->debug($message, (array) $context);
    }
}
