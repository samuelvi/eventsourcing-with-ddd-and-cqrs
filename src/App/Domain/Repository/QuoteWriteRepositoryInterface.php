<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\QuoteEntity;

interface QuoteWriteRepositoryInterface
{
    public function save(QuoteEntity $quote): void;
}
