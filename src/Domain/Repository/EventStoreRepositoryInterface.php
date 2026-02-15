<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\AggregateRootInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @template T of AggregateRootInterface
 */
interface EventStoreRepositoryInterface
{
    /** @param T $aggregate */
    public function save(AggregateRootInterface $aggregate): void;

    /** @return T|null */
    public function get(Uuid $id): ?AggregateRootInterface;
}
