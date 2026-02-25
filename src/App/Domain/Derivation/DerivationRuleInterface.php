<?php

declare(strict_types=1);

namespace App\Domain\Derivation;

interface DerivationRuleInterface
{
    public function allows(DerivationContext $context): bool;
}
