<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Selection;

use App\Domain\Derivation\Candidate\QuoteCandidate;

final readonly class QuoteSelectionHandler
{
    private const DEFAULT_LIMIT = 4;

    public function __construct(
        private int $quoteLimit = self::DEFAULT_LIMIT,
    ) {}

    /**
     * @param array<int, QuoteCandidate> $candidates
     * @return array<int, QuoteCandidate>
     */
    public function limit(array $candidates): array
    {
        return array_slice($candidates, 0, $this->quoteLimit);
    }

    public function quoteLimit(): int
    {
        return $this->quoteLimit;
    }
}
