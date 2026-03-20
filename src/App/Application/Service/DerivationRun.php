<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Shared\TypeAssert;

final readonly class DerivationRun
{
    public const STATUS_OPENED = 'opened';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_NO_BOOKING_FACTS = 'completed_no_booking_facts';
    public const STATUS_COMPLETED_NO_CANDIDATES = 'completed_no_candidates';

    private function __construct(
        public string $derivationRunId,
        public string $bookingId,
        public string $correlationId,
        public string $status,
        public \DateTimeImmutable $openedAt,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public static function open(DerivationRunContext $context, ?\DateTimeImmutable $openedAt = null): self
    {
        $timestamp = $openedAt ?? new \DateTimeImmutable();

        return new self(
            derivationRunId: $context->derivationRunId,
            bookingId: $context->bookingId,
            correlationId: $context->correlationId,
            status: self::STATUS_OPENED,
            openedAt: $timestamp,
            updatedAt: $timestamp,
        );
    }

    public function withStatus(string $status, ?\DateTimeImmutable $updatedAt = null): self
    {
        return new self(
            derivationRunId: $this->derivationRunId,
            bookingId: $this->bookingId,
            correlationId: $this->correlationId,
            status: $status,
            openedAt: $this->openedAt,
            updatedAt: $updatedAt ?? new \DateTimeImmutable(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            derivationRunId: TypeAssert::string($data['derivationRunId']),
            bookingId: TypeAssert::string($data['bookingId']),
            correlationId: TypeAssert::string($data['correlationId']),
            status: TypeAssert::string($data['status']),
            openedAt: new \DateTimeImmutable(TypeAssert::string($data['openedAt'])),
            updatedAt: new \DateTimeImmutable(TypeAssert::string($data['updatedAt'])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'derivationRunId' => $this->derivationRunId,
            'bookingId' => $this->bookingId,
            'correlationId' => $this->correlationId,
            'status' => $this->status,
            'openedAt' => $this->openedAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
