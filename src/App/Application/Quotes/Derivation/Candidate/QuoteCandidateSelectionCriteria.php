<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Candidate;

use App\Domain\Derivation\Candidate\QuoteCandidate;
use App\Domain\ValueObject\UuidString;

final readonly class QuoteCandidateSelectionCriteria
{
    /**
     * @var array<int, string>
     */
    public array $supplierIds;

    /**
     * @var array<int, string>
     */
    public array $productIds;

    /**
     * @param array<int, string> $supplierIds
     * @param array<int, string> $productIds
     */
    public function __construct(
        array $supplierIds = [],
        array $productIds = [],
    ) {
        $this->supplierIds = self::normalizeIds($supplierIds);
        $this->productIds = self::normalizeIds($productIds);
    }

    public function hasFilters(): bool
    {
        return $this->supplierIds !== [] || $this->productIds !== [];
    }

    public function allows(QuoteCandidate $candidate): bool
    {
        if ($this->supplierIds !== [] && !in_array($candidate->supplierId, $this->supplierIds, true)) {
            return false;
        }

        if ($this->productIds !== [] && !in_array($candidate->productId, $this->productIds, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, string> $ids
     * @return array<int, string>
     */
    private static function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_map(
            static fn(string $id): string => UuidString::fromString($id)->toString(),
            $ids,
        )));
    }
}
