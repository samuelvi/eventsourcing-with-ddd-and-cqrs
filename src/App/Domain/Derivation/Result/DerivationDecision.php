<?php

declare(strict_types=1);

namespace App\Domain\Derivation\Result;

final readonly class DerivationDecision
{
    public function __construct(
        public bool $allowed,
        public ?string $failedRuleClass = null,
    ) {}
}
