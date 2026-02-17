<?php

declare(strict_types=1);

namespace App\Test\Infrastructure\Http\Controller;

use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Mongo\MongoStore;
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
    ): Response
    {
        // 1. Truncate SQL Read Models
        $readEntityManager->execute('TRUNCATE users, bookings, products, menus, suppliers, quotes RESTART IDENTITY CASCADE');

        // 2. Clear Mongo (Events, Checkpoints, Snapshots)
        $mongoStore->clearAll();

        return new JsonResponse(['status' => 'success']);
    }
}
