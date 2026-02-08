<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\Service\ArchitectureControlService;
use App\Domain\Model\Snapshot;
use App\Domain\Repository\BookingReadRepositoryInterface;
use App\Domain\Repository\UserReadRepositoryInterface;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Uid\Uuid;

final class DemoControlController extends AbstractController
{
    private const CACHE_KEY_MASTER = 'demo_projections_enabled';
    private const CACHE_KEY_USER_PROJECTIONS = 'demo_user_projections_enabled';
    private const CACHE_KEY_BOOKING_PROJECTIONS = 'demo_booking_projections_enabled';

    public function __construct(
        private CacheInterface $cache,
        private MongoStore $mongoStore,
        private UserReadRepositoryInterface $userRepository,
        private BookingReadRepositoryInterface $bookingRepository,
        private ArchitectureControlService $architectureService,
    ) {}

    #[Route('/api/demo/status', methods: ['GET'])]
    public function getStatus(): Response
    {
        $masterEnabled = $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $userEnabled = $this->cache->get(self::CACHE_KEY_USER_PROJECTIONS, fn() => true);
        $bookingEnabled = $this->cache->get(self::CACHE_KEY_BOOKING_PROJECTIONS, fn() => true);
        
        return new JsonResponse([
            'projectionsEnabled' => $masterEnabled,
            'userProjectionsEnabled' => $userEnabled,
            'bookingProjectionsEnabled' => $bookingEnabled
        ]);
    }

    #[Route('/api/demo/toggle/{type}', methods: ['POST'])]
    public function toggle(string $type): Response
    {
        $key = match($type) {
            'master' => self::CACHE_KEY_MASTER,
            'user' => self::CACHE_KEY_USER_PROJECTIONS,
            'booking' => self::CACHE_KEY_BOOKING_PROJECTIONS,
            default => throw new \InvalidArgumentException('Invalid type')
        };
        
        $current = $this->cache->get($key, fn() => true);
        $newValue = !$current;

        $this->cache->delete($key);
        $this->cache->get($key, fn() => $newValue);

        return new JsonResponse([
            ($type === 'master' ? 'projectionsEnabled' : ($type . 'ProjectionsEnabled')) => $newValue
        ]);
    }

    #[Route('/api/demo/rebuild', methods: ['POST'])]
    public function rebuild(): Response
    {
        $processedCount = $this->architectureService->rebuild();
        return new JsonResponse(['status' => 'success', 'processed' => $processedCount]);
    }

    #[Route('/api/demo/stats', methods: ['GET'])]
    public function getStats(): Response
    {
        $eventCount = $this->mongoStore->countEvents();
        $userCount = $this->userRepository->countAll();
        $bookingCount = $this->bookingRepository->countAll();
        $snapshotCount = $this->mongoStore->countSnapshots();

        $checkpoints = $this->mongoStore->findAllCheckpoints();
        $checkpointsMap = [];
        foreach ($checkpoints as $cp) {
            $checkpointsMap[$cp->projectionName] = $cp->lastEventId?->toRfc4122();
        }

        return new JsonResponse([
            'events' => $eventCount,
            'users' => $userCount,
            'bookings' => $bookingCount,
            'snapshots' => $snapshotCount,
            'checkpoints' => $checkpointsMap
        ]);
    }

    #[Route('/api/demo/snapshot', methods: ['POST'])]
    public function snapshot(): Response
    {
        $eventCount = $this->mongoStore->countEvents();
        $userCount = $this->userRepository->countAll();
        $bookingCount = $this->bookingRepository->countAll();

        $snapshot = Snapshot::take(
            Uuid::v7(), // System aggregate ID for demo
            $eventCount,
            ['users' => $userCount, 'bookings' => $bookingCount, 'timestamp' => time()]
        );

        $this->mongoStore->saveSnapshot($snapshot);

        return new JsonResponse(['status' => 'success', 'version' => $eventCount]);
    }

    #[Route('/api/demo/reset', methods: ['POST'])]
    public function reset(): Response
    {
        try {
            $this->architectureService->reset();
            return new JsonResponse(['status' => 'success']);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}