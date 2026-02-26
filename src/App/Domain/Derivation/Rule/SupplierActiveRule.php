<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Rule;

use App\Domain\Derivation\DerivationContext;
use App\Domain\Derivation\Rule\Common\AbstractDerivationRule;

final readonly class SupplierActiveRule extends AbstractDerivationRule
{
    protected function evaluate(DerivationContext $context): bool
    {
        return $context->supplier->isActive;
    }
}
