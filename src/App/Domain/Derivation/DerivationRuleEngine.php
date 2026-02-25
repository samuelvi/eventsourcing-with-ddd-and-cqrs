<?php

declare(strict_types=1);

namespace App\Domain\Derivation;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final readonly class DerivationRuleEngine
{
    /**
     * @param iterable<DerivationRuleInterface> $rules
     */
    public function __construct(
        #[TaggedIterator('app.derivation_rule')]
        private iterable $rules,
    ) {}

    public function allows(DerivationContext $context): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->allows($context)) {
                return false;
            }
        }

        return true;
    }
}
