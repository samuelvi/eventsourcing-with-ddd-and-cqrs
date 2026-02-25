<?php

declare(strict_types=1);

namespace App\Domain\Derivation;

final readonly class CountryRule implements DerivationRuleInterface
{
    public function allows(DerivationContext $context): bool
    {
        if ($context->bookingCountry === null || $context->bookingCountry === '') {
            return true;
        }

        if ($context->supplierCountry === null || $context->supplierCountry === '') {
            return false;
        }

        return strtoupper($context->bookingCountry) === strtoupper($context->supplierCountry);
    }
}
