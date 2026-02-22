<?php

declare(strict_types=1);

namespace App\Application\Bus;

interface AsyncCommandBusInterface
{
    public function dispatch(object $command): void;
}

