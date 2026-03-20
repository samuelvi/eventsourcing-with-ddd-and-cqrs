<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Process;

use App\Application\Service\DerivationRunContext;

interface SupplierProcessStarterInterface
{
    /**
     * @param array<int, string> $quoteIds
     */
    public function start(DerivationRunContext $context, array $quoteIds): void;
}
