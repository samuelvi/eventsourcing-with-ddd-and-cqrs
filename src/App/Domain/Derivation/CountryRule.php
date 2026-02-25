<?php

declare(strict_types=1);

namespace App\Domain\Derivation;

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
