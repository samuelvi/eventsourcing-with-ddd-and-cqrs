<?php

declare(strict_types=1);

namespace App\Integrations\N8n\Application\Service\SupplierProcess;

use App\Domain\Repository\QuoteWriteRepositoryInterface;
use Psr\Log\LoggerInterface;

final readonly class SupplierResponseProcessCallbackUrlRegistrar
{
    public function __construct(
        private QuoteWriteRepositoryInterface $quoteWriteRepository,
        private ?LoggerInterface $n8nLogger = null,
    ) {}

    public function register(
        string $quoteId,
        string $callbackUrl,
        ?string $derivationRunId = null,
        ?string $correlationId = null,
    ): int
    {
        $updated = $this->quoteWriteRepository->callbackUpdate($quoteId, $callbackUrl);

        $this->n8nLogger?->info('Registered supplier response callback url', [
            'quoteId' => $quoteId,
            'derivationRunId' => $derivationRunId,
            'correlationId' => $correlationId,
            'updatedQuotes' => $updated,
        ]);

        return $updated;
    }
}
