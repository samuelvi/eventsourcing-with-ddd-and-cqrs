<?php

declare(strict_types=1);

namespace App\Test\Infrastructure\Http\Controller;

use App\Application\Service\ArchitectureControlService;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for E2E tests to reset the system state.
 * ONLY ENABLED IN TEST ENV.
 */
final class TestResetController extends AbstractController
{
    public function __construct(
        private ReadEntityManager $readEntityManager,
        private MongoStore $mongoStore,
    ) {}

    #[Route('/api/test/reset-db-empty', name: 'api_test_reset_db_empty', methods: ['POST'])]
    public function reset(): Response
    {
        if ($this->getParameter('kernel.environment') !== 'test') {
            return new JsonResponse(['error' => 'Only available in test environment'], Response::HTTP_FORBIDDEN);
        }

        // 1. Truncate SQL Read Models
        $this->readEntityManager->execute('TRUNCATE users, bookings, products, menus, suppliers, quotes RESTART IDENTITY CASCADE');

        // 2. Clear Mongo (Events, Checkpoints, Snapshots)
        $this->mongoStore->clearAll();

        return new JsonResponse(['status' => 'success']);
    }
}
