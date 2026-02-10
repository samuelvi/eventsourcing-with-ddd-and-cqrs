<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Model\BookingEntity;
use App\Domain\Repository\BookingWriteRepositoryInterface;

/**
 * @implements ProcessorInterface<BookingEntity, BookingEntity>
 */
final readonly class BookingMarkAsProcessedProcessor implements ProcessorInterface
{
    public function __construct(
        private BookingWriteRepositoryInterface $writeRepository,
    ) {}

    /**
     * @param BookingEntity $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BookingEntity
    {
        $data->markAsProcessedByN8n();
        $this->writeRepository->save($data);

        return $data;
    }
}
