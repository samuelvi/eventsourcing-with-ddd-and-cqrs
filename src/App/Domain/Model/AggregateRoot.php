<?php

declare(strict_types=1);

namespace App\Domain\Model;

use Symfony\Component\Uid\Uuid;

abstract class AggregateRoot implements AggregateRootInterface
{
    /** @var object[] */
    private array $recordedEvents = [];
    protected int $version = 0;

    protected function __construct(
        protected Uuid $aggregateId
    ) {}

    public function getAggregateId(): Uuid
    {
        return $this->aggregateId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getRecordedEvents(): array
    {
        return $this->recordedEvents;
    }

    public function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }

    protected function recordThat(object $event): void
    {
        $this->recordedEvents[] = $event;
        $this->apply($event);
        $this->version++;
    }

    abstract protected function apply(object $event): void;

    public static function reconstituteFromHistory(Uuid $id, array $events, ?AggregateRootInterface $aggregate = null): static
    {
        /** @var static $aggregate */
        $aggregate = $aggregate ?? new static($id);
        foreach ($events as $event) {
            $aggregate->apply($event);
            $aggregate->version++;
        }

        return $aggregate;
    }

    /** @return array<string, mixed> */
    abstract public function getSnapshotState(): array;

    /** @param array<string, mixed> $state */
    public static function reconstituteFromSnapshot(Uuid $id, int $version, array $state): static
    {
        $aggregate = new static($id);
        $aggregate->version = $version;
        $aggregate->applySnapshot($state);

        return $aggregate;
    }

    /** @param array<string, mixed> $state */
    abstract protected function applySnapshot(array $state): void;
}
