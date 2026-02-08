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
    public function __construct(
        private ArchitectureControlService $architectureService,
    ) {}

    #[Route('/api/demo/status', methods: ['GET'])]
    public function getStatus(): Response
    {
        return new JsonResponse($this->architectureService->getStatus());
    }

    #[Route('/api/demo/toggle/{type}', methods: ['POST'])]
    public function toggle(string $type): Response
    {
        $newValue = $this->architectureService->toggle($type);
        
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
        return new JsonResponse($this->architectureService->getStats());
    }

    #[Route('/api/demo/snapshot', methods: ['POST'])]
    public function snapshot(): Response
    {
        $version = $this->architectureService->takeSnapshot();
        return new JsonResponse(['status' => 'success', 'version' => $version]);
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