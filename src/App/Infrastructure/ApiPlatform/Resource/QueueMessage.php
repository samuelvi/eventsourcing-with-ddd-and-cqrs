<?php

declare(strict_types=1);

namespace App\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Infrastructure\ApiPlatform\Provider\QueueMessageProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'QueueMessage',
    operations: [
        new Get(uriTemplate: '/messages/{id}', provider: QueueMessageProvider::class),
        new GetCollection(uriTemplate: '/messages', provider: QueueMessageProvider::class, paginationEnabled: false),
        new GetCollection(uriTemplate: '/messages-async', provider: QueueMessageProvider::class, paginationEnabled: false),
        new GetCollection(uriTemplate: '/messages-failed', provider: QueueMessageProvider::class, paginationEnabled: false)
    ],
    normalizationContext: ['groups' => ['message:read']]
)]
final readonly class QueueMessage
{
    public function __construct(
        #[Groups(['message:read'])]
        public int $id,
        #[Groups(['message:read'])]
        public string $body,
        #[Groups(['message:read'])]
        public string $headers,
        #[Groups(['message:read'])]
        public string $queueName,
        #[Groups(['message:read'])]
        public string $createdAt,
        #[Groups(['message:read'])]
        public string $availableAt,
        #[Groups(['message:read'])]
        public ?string $deliveredAt,
    ) {}
}
