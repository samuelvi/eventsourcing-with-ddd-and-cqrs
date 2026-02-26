<?php

declare(strict_types=1);

namespace App\Domain\Derivation;

use App\Domain\Derivation\Result\DerivationDecision;
use App\Domain\Derivation\Rule\Interface\DerivationRuleInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final readonly class DerivationRuleEngine
{
    /**
     * @param iterable<DerivationRuleInterface> $activeRules
     */
    public function __construct(
        #[TaggedIterator('app.derivation_rule')]
        private iterable $activeRules,
    ) {}

    public function allows(DerivationContext $context): bool
    {
        return $this->decide($context)->allowed;
    }

    public function decide(DerivationContext $context): DerivationDecision
    {
        foreach ($this->activeRules as $rule) {
            $ruleClass = $rule::class;

            if (!$rule->allows($context)) {
                return new DerivationDecision(false, $ruleClass);
            }
        }

        return new DerivationDecision(true);
    }
}
