<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Ranking;

use App\Domain\Derivation\Candidate\QuoteCandidate;

final readonly class QuoteRankingHandler
{
    /**
     * @param array<int, QuoteCandidate> $candidates
     * @return array<int, QuoteCandidate>
     */
    public function rank(array $candidates): array
    {
        usort(
            $candidates,
            static function (QuoteCandidate $left, QuoteCandidate $right): int {
                if ($left->price !== $right->price) {
                    return $right->price <=> $left->price;
                }

                $supplierComparison = strcmp($left->supplierId, $right->supplierId);
                if ($supplierComparison !== 0) {
                    return $supplierComparison;
                }

                return strcmp($left->productId, $right->productId);
            },
        );

        return $candidates;
    }
}
