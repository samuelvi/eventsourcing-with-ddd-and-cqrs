<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\State\ProviderInterface;
use App\Infrastructure\ApiPlatform\Resource\QueueMessage;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * @implements ProviderInterface<QueueMessage>
 */
final readonly class QueueMessageProvider implements ProviderInterface
{
    public function __construct(
        #[Target('messaging')]
        private Connection $connection,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $uriTemplate = $operation instanceof HttpOperation ? $operation->getUriTemplate() : null;
        $filterQueue = null;
        if ($uriTemplate === '/messages-async') {
            $filterQueue = 'async';
        } elseif ($uriTemplate === '/messages-failed') {
            $filterQueue = 'failed';
        }

        if (isset($uriVariables['id'])) {
            $data = $this->connection->fetchAssociative(
                'SELECT * FROM messenger_messages WHERE id = :id',
                ['id' => $uriVariables['id']]
            );

            if (!$data) {
                return null;
            }

            return $this->mapToModel($data);
        }

        try {
            if ($filterQueue) {
                $data = $this->connection->fetchAllAssociative(
                    'SELECT * FROM messenger_messages WHERE queue_name = :queue ORDER BY id DESC',
                    ['queue' => $filterQueue]
                );
            } else {
                $data = $this->connection->fetchAllAssociative('SELECT * FROM messenger_messages ORDER BY id DESC');
            }
        } catch (\Exception $e) {
            // Table might not exist yet if no messages were sent
            return [];
        }

        return array_map($this->mapToModel(...), $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function mapToModel(array $data): QueueMessage
    {
        return new QueueMessage(
            $this->toIntOrZero($data['id'] ?? null),
            $this->toStringOrEmpty($data['body'] ?? null),
            $this->toStringOrEmpty($data['headers'] ?? null),
            $this->toStringOrEmpty($data['queue_name'] ?? null),
            $this->toStringOrEmpty($data['created_at'] ?? null),
            $this->toStringOrEmpty($data['available_at'] ?? null),
            $this->toNullableString($data['delivered_at'] ?? null),
        );
    }

    private function toIntOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function toStringOrEmpty(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->toStringOrEmpty($value);
    }
}
