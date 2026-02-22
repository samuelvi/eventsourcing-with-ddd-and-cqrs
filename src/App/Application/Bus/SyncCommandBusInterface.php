<?php

declare(strict_types=1);

namespace App\Application\Bus;

interface SyncCommandBusInterface
{
    public function dispatch(object $command): void;
}

