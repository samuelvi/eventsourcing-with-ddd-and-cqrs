<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
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
        $uriTemplate = $operation->getUriTemplate();
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
            (int) $data['id'],
            (string) $data['body'],
            (string) $data['headers'],
            (string) $data['queue_name'],
            (string) $data['created_at'],
            (string) $data['available_at'],
            isset($data['delivered_at']) ? (string) $data['delivered_at'] : null,
        );
    }
}
