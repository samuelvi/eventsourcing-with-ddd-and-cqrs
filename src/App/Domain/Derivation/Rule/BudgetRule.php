<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Rule;

use App\Domain\Derivation\DerivationContext;
use App\Domain\Derivation\Rule\Common\AbstractDerivationRule;

final readonly class BudgetRule extends AbstractDerivationRule
{
    protected function evaluate(DerivationContext $context): bool
    {
        $budget = $context->booking->budget;
        $price = $context->product->price;

        if ($budget <= 0) {
            return true;
        }

        return $price <= $budget;
    }
}
