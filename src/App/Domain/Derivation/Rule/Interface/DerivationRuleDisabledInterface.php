<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Rule\Interface;

use App\Domain\Derivation\DerivationContext;

interface DerivationRuleDisabledInterface
{
    public function allows(DerivationContext $context): bool;
}
