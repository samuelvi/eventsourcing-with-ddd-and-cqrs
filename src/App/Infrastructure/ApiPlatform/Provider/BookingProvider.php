<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\BookingEntity;
use App\Domain\Model\UserEntity;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Domain\Repository\UserReadRepositoryInterface;
use App\Domain\Shared\TypeAssert;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<BookingEntity>
 */
final readonly class BookingProvider implements ProviderInterface
{
    public function __construct(
        private BookingReadRepositoryInterface $repository,
        private UserReadRepositoryInterface $userRepository,
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

        // Custom filter: /api/bookings?pending=true
        $filters = TypeAssert::array($context['filters'] ?? []);
        if (isset($filters['pending']) && $filters['pending'] === 'true') {
            $data = $this->repository->findPending();
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

        $userId = TypeAssert::string($row['user_id']);
        $userRow = $this->userRepository->findById($userId);
        
        if (!$userRow) {
            throw new \RuntimeException(sprintf('User %s not found for booking %s', $userId, TypeAssert::string($row['id'] ?? null)));
        }

        $user = UserEntity::hydrate(
            $userRow['name'],
            $userRow['email'],
            Uuid::fromString($userRow['id']),
            isset($userRow['created_at']) ? new \DateTimeImmutable(TypeAssert::string($userRow['created_at'])) : null,
            $userRow['address'] ?? null
        );
        
        $booking = BookingEntity::hydrate(
            Uuid::fromString(TypeAssert::string($row['id'])),
            $user,
            $bookingData,
            new \DateTimeImmutable(TypeAssert::string($row['created_at'])),
            TypeAssert::string($row['status'] ?? BookingEntity::STATUS_PENDING)
        );

        $booking->country = isset($row['country']) ? TypeAssert::string($row['country']) : null;

        return $booking;
    }
}
