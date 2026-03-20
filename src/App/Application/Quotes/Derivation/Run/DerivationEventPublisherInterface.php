<?php

declare(strict_types=1);

namespace App\Application\Quotes\Derivation\Run;

use App\Domain\Derivation\Event\QuoteCreated;
use App\Domain\Derivation\Event\QuoteFlowFinsih;
use App\Domain\Derivation\Event\QuoteLimited;
use App\Domain\Derivation\Event\QuoteLimitedByRules;
use App\Domain\Derivation\Event\QuoteNotified;
use App\Domain\Derivation\Event\StartQuoteProcess;

interface DerivationEventPublisherInterface
{
    public function publishLimited(QuoteLimited $event): void;

    public function publishFlowFinished(QuoteFlowFinsih $event): void;

    public function publishLimitedByRules(QuoteLimitedByRules $event): void;

    public function publishCreated(QuoteCreated $event): void;

    public function publishStartQuoteProcess(StartQuoteProcess $event): void;

    public function publishNotified(QuoteNotified $event): void;
}
