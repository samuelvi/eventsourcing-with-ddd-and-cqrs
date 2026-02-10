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

            return $this->hydrate($row);
        }

        // Custom filter for n8n: /api/bookings?pending=true
        $filters = TypeAssert::array($context['filters'] ?? []);
        if (isset($filters['pending']) && $filters['pending'] === 'true') {
            $data = $this->repository->findPendingForN8n();
        } else {
            $data = $this->repository->findAllForList();
        }

        return array_map(fn(array $row) => $this->hydrate($row), $data);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): BookingEntity
    {
        /** @var array<string, mixed> $bookingData */
        $bookingData = TypeAssert::array(json_decode(TypeAssert::string($row['data']), true));
        
        return BookingEntity::hydrate(
            Uuid::fromString(TypeAssert::string($row['id'])),
            $bookingData,
            new \DateTimeImmutable(TypeAssert::string($row['created_at'])),
            (bool) ($row['processed_by_n8n'] ?? false)
        );
    }
}