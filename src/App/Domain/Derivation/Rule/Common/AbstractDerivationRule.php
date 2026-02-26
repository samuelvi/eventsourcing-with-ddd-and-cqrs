<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Rule\Common;

use App\Domain\Derivation\DerivationContext;
use App\Domain\Derivation\DerivationRuleInterface;

abstract readonly class AbstractDerivationRule implements DerivationRuleInterface
{
    abstract protected function evaluate(DerivationContext $context): bool;

    public function allows(DerivationContext $context): bool
    {
        return $this->evaluate($context);
    }
}
