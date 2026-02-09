<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\BookingEntity;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Domain\Shared\TypeAssert;
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
            $row = $this->repository->findById(TypeAssert::string($uriVariables['id']));
            
            if (!$row) {
                return null;
            }

            /** @var array<string, mixed> $bookingData */
            $bookingData = json_decode(TypeAssert::string($row['data']), true);

            return BookingEntity::hydrate(
                Uuid::fromString(TypeAssert::string($row['id'])),
                $bookingData,
                new \DateTimeImmutable(TypeAssert::string($row['created_at']))
            );
        }

        $data = $this->repository->findAllForList();

        return array_map(function (array $row) {
            /** @var array<string, mixed> $bookingData */
            $bookingData = json_decode(TypeAssert::string($row['data']), true);
            
            return BookingEntity::hydrate(
                Uuid::fromString(TypeAssert::string($row['id'])),
                $bookingData,
                new \DateTimeImmutable(TypeAssert::string($row['created_at']))
            );
        }, $data);
    }
}
