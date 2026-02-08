<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\BookingEntity;
use App\Domain\Repository\BookingReadRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<BookingEntity>
 */
final readonly class BookingProvider implements ProviderInterface
{
    public function __construct(
        private BookingReadRepositoryInterface $repository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            // Not strictly needed for the demo tables, but for completeness:
            // We could implement findById if needed.
            return null;
        }

        return $this->repository->findAllForList();
    }
}
