<?php

declare(strict_types=1);

namespace App\Test\Infrastructure\Http\Controller;

use App\Application\Service\ArchitectureControlService;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for E2E tests to reset the system state.
 * ONLY ENABLED IN TEST ENV.
 */
#[AsController]
final class TestResetController
{
    #[Route('/api/test/reset-db-empty', name: 'api_test_reset_db_empty', methods: ['POST'])]
    public function reset(
        ReadEntityManager $readEntityManager,
        MongoStore $mongoStore,
        ArchitectureControlService $architectureControlService,
        #[Target('messaging.connection')]
        Connection $messagingConnection,
        #[Autowire('%env(string:QUEUE_BACKEND)%')]
        string $queueBackend,
    ): Response
    {
        // Reset projection toggles to avoid cross-scenario state leakage.
        $architectureControlService->enableAll();

        // 1. Truncate SQL Read Models
        $readEntityManager->execute('TRUNCATE users, bookings, products, menus, suppliers, quotes RESTART IDENTITY CASCADE');

        // 2. Clear Mongo (Events, Checkpoints, Snapshots)
        $mongoStore->clearAll();

        // 3. Clear messenger queues (async + failed) in test environment.
        // Table might not exist yet in very early test bootstrap.
        if ($queueBackend === 'postgres') {
            try {
                $messagingConnection->executeStatement('TRUNCATE messenger_messages RESTART IDENTITY');
            } catch (\Throwable) {
                // Ignore missing table/connection issues in reset helper.
            }
        }

        return new JsonResponse(['status' => 'success']);
    }
}
