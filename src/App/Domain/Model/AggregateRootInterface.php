<?php

declare(strict_types=1);

namespace App\Domain\Model;

use Symfony\Component\Uid\Uuid;

interface AggregateRootInterface
{
    public function getAggregateId(): Uuid;
    public function getVersion(): int;
    
    /** @return object[] */
    public function getRecordedEvents(): array;
    public function clearRecordedEvents(): void;
    
    /** @param object[] $events */
    public static function reconstituteFromHistory(Uuid $id, array $events, ?AggregateRootInterface $aggregate = null): AggregateRootInterface;

    /** @return array<string, mixed> */
    public function getSnapshotState(): array;

    /** @param array<string, mixed> $state */
    public static function reconstituteFromSnapshot(Uuid $id, int $version, array $state): AggregateRootInterface;
}
