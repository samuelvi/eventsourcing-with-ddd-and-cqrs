<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Rule\Interface;

use App\Domain\Derivation\DerivationContext;

interface DerivationRuleInterface
{
    public function allows(DerivationContext $context): bool;
}
