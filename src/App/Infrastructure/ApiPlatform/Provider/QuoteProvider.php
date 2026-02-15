<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Model\QuoteEntity;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Domain\Shared\TypeAssert;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<QuoteEntity>
 */
final readonly class QuoteProvider implements ProviderInterface
{
    public function __construct(
        private ReadEntityManager $entityManager,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (isset($uriVariables['id'])) {
            $id = $uriVariables['id'];
            $sql = 'SELECT * FROM quotes WHERE id = :id';
            $data = $this->entityManager->fetchOne($sql, ['id' => $id]);

            if (!$data) {
                return null;
            }

            return $this->hydrate($data);
        }

        // Collection
        $sql = 'SELECT * FROM quotes ORDER BY created_at DESC';
        $rows = $this->entityManager->query($sql);
        $entities = [];

        foreach ($rows as $row) {
            $entities[] = $this->hydrate($row);
        }

        return $entities;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): QuoteEntity
    {
        return QuoteEntity::hydrate(
            Uuid::fromString(TypeAssert::string($data['id'])),
            Uuid::fromString(TypeAssert::string($data['booking_id'])),
            Uuid::fromString(TypeAssert::string($data['supplier_id'])),
            Uuid::fromString(TypeAssert::string($data['product_id'])),
            TypeAssert::float($data['price']),
            new \DateTimeImmutable(TypeAssert::string($data['created_at']))
        );
    }
}
