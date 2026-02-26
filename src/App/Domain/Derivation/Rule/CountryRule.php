<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Rule;

use App\Domain\Derivation\DerivationContext;
use App\Domain\Derivation\Rule\Interface\DerivationRuleInterface;

final readonly class CountryRule implements DerivationRuleInterface
{
    public function allows(DerivationContext $context): bool
    {
        if ($context->booking->country === null || $context->booking->country === '') {
            return true;
        }

        if ($context->supplier->country === null || $context->supplier->country === '') {
            return false;
        }

        return strtoupper($context->booking->country) === strtoupper($context->supplier->country);
    }
}
