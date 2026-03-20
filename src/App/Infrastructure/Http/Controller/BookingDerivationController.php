<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\Service\DerivationRunTracker;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class BookingDerivationController
{
    public function __construct(
        private MongoStore $mongoStore,
        private DerivationRunTracker $derivationRunTracker,
    ) {}

    #[Route('/api/bookings/{bookingId}/derivation-history', name: 'api_booking_derivation_history', methods: ['GET'])]
    public function __invoke(string $bookingId): JsonResponse
    {
        $events = $this->mongoStore->findEventsByBookingId($bookingId);

        $history = array_map(function ($event) {
            return [
                'eventType' => $event->eventType,
                'derivationRunId' => $event->payload['derivationRunId'] ?? null,
                'quoteId' => $event->payload['quoteId'] ?? null,
                'supplierId' => $event->payload['supplierId'] ?? null,
                'productId' => $event->payload['productId'] ?? null,
                'requestedPrice' => $event->payload['requestedPrice'] ?? null,
                'correlationId' => $event->payload['correlationId'] ?? null,
                'occurredOn' => $event->occurredOn->format(\DateTimeInterface::ATOM),
            ];
        }, $events);

        $runs = array_map(static function ($run): array {
            return [
                'derivationRunId' => $run->derivationRunId,
                'bookingId' => $run->bookingId,
                'correlationId' => $run->correlationId,
                'status' => $run->status,
                'openedAt' => $run->openedAt->format(\DateTimeInterface::ATOM),
                'updatedAt' => $run->updatedAt->format(\DateTimeInterface::ATOM),
            ];
        }, $this->derivationRunTracker->findByBookingId($bookingId));

        return new JsonResponse([
            'bookingId' => $bookingId,
            'runCount' => count($runs),
            'derivationCount' => count($events),
            'runs' => $runs,
            'history' => $history,
        ]);
    }
}
