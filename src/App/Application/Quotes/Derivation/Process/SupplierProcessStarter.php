<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Process;

use App\Application\Service\DerivationRunContext;
use App\Integrations\N8n\Application\Service\SupplierProcess\QuoteStartedProcess;

final readonly class SupplierProcessStarter implements SupplierProcessStarterInterface
{
    public function __construct(
        private QuoteStartedProcess $quoteStartedProcess,
    ) {}

    /**
     * @param array<int, string> $quoteIds
     */
    public function start(DerivationRunContext $context, array $quoteIds): void
    {
        $this->quoteStartedProcess->notify($context, $quoteIds);
    }
}
