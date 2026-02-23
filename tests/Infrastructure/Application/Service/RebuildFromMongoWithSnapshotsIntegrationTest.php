<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Application\Service;

use App\Application\Service\ArchitectureControlService;
use App\Domain\Model\User;
use App\Domain\Repository\UserEventStoreRepositoryInterface;
use App\Domain\Repository\UserReadRepositoryInterface;
use App\Domain\ValueObject\Address;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\PersonName;
use App\Infrastructure\EventSourcing\Snapshot;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Mongo\MongoClient;
use App\Infrastructure\Persistence\Mongo\MongoStore;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class RebuildFromMongoWithSnapshotsIntegrationTest extends KernelTestCase
{
    private ArchitectureControlService $architectureControlService;
    private UserEventStoreRepositoryInterface $userEventStoreRepository;
    private UserReadRepositoryInterface $userReadRepository;
    private ReadEntityManager $readEntityManager;
    private MongoStore $mongoStore;
    private MongoClient $mongoClient;

    protected function setUp(): void
    {
        if (!class_exists(\MongoDB\Driver\Manager::class)) {
            $this->markTestSkipped('ext-mongodb is not available in this environment.');
        }

        self::bootKernel();

        /** @var ArchitectureControlService $architectureControlService */
        $architectureControlService = self::getContainer()->get(ArchitectureControlService::class);
        $this->architectureControlService = $architectureControlService;

        /** @var UserEventStoreRepositoryInterface $userEventStoreRepository */
        $userEventStoreRepository = self::getContainer()->get(UserEventStoreRepositoryInterface::class);
        $this->userEventStoreRepository = $userEventStoreRepository;

        /** @var UserReadRepositoryInterface $userReadRepository */
        $userReadRepository = self::getContainer()->get(UserReadRepositoryInterface::class);
        $this->userReadRepository = $userReadRepository;

        /** @var ReadEntityManager $readEntityManager */
        $readEntityManager = self::getContainer()->get(ReadEntityManager::class);
        $this->readEntityManager = $readEntityManager;

        /** @var MongoStore $mongoStore */
        $mongoStore = self::getContainer()->get(MongoStore::class);
        $this->mongoStore = $mongoStore;

        /** @var MongoClient $mongoClient */
        $mongoClient = self::getContainer()->get(MongoClient::class);
        $this->mongoClient = $mongoClient;

        if (!$this->isInfrastructureReachable()) {
            $this->markTestSkipped('Postgres/Mongo test infrastructure is not reachable in this environment.');
        }

        $this->readEntityManager->execute(
            'TRUNCATE users, bookings, products, menus, suppliers, quotes RESTART IDENTITY CASCADE'
        );
        $this->mongoStore->clearAll();
    }

    public function testRebuildRestoresReadModelUsingEventsAndSnapshotsAfterSqlDataLoss(): void
    {
        $userId = Uuid::v7();

        $aggregate = User::register(
            $userId,
            PersonName::fromString('Alice v1'),
            Email::fromString('alice@example.com'),
            Address::fromString('Street 1')
        );
        $this->userEventStoreRepository->save($aggregate);

        // Build history up to v5 and create an explicit snapshot at v5.
        $aggregate->updateProfile(
            PersonName::fromString('Alice v2'),
            Email::fromString('alice@example.com'),
            Address::fromString('Street 2')
        );
        $this->userEventStoreRepository->save($aggregate);
        $aggregate->updateProfile(
            PersonName::fromString('Alice v3'),
            Email::fromString('alice@example.com'),
            Address::fromString('Street 3')
        );
        $this->userEventStoreRepository->save($aggregate);
        $aggregate->updateProfile(
            PersonName::fromString('Alice v4'),
            Email::fromString('alice@example.com'),
            Address::fromString('Street 4')
        );
        $this->userEventStoreRepository->save($aggregate);
        $aggregate->updateProfile(
            PersonName::fromString('Alice v5'),
            Email::fromString('alice@example.com'),
            Address::fromString('Street 5')
        );
        $this->userEventStoreRepository->save($aggregate);

        $v5State = $this->userEventStoreRepository->get($userId);
        self::assertInstanceOf(User::class, $v5State);
        $this->mongoStore->saveSnapshot(
            Snapshot::take($userId, $v5State->getVersion(), $v5State->getSnapshotState())
        );

        // Add one delta event after snapshot (v6).
        $v5State->updateProfile(
            PersonName::fromString('Alice v6'),
            Email::fromString('alice@example.com'),
            Address::fromString('Street 6')
        );
        $this->userEventStoreRepository->save($v5State);

        self::assertGreaterThan(0, $this->mongoStore->countEvents());
        self::assertGreaterThan(0, $this->mongoStore->countSnapshots());

        // Keep only the delta event in Mongo to force recovery from snapshot + event delta.
        $this->mongoClient->getDatabase()->selectCollection('events')->deleteMany([
            'aggregateId' => $userId->toRfc4122(),
            'version' => ['$lte' => 5],
        ]);

        // 1) Ensure SQL has data before simulated loss.
        $this->architectureControlService->rebuildFromMongo();
        $projectedBeforeLoss = $this->userReadRepository->findById($userId->toRfc4122());
        self::assertNotNull($projectedBeforeLoss);
        self::assertSame('Alice v6', $projectedBeforeLoss['name']);
        self::assertSame('Street 6', $projectedBeforeLoss['address']);

        // 2) Simulate SQL read-model loss.
        $this->architectureControlService->clearTransactionalData();
        self::assertSame(0, $this->userReadRepository->countAll());

        // 3) Recover from Mongo (events + snapshots).
        $this->architectureControlService->rebuildFromMongo();

        $projectedAfterRecovery = $this->userReadRepository->findById($userId->toRfc4122());
        self::assertNotNull($projectedAfterRecovery);
        self::assertSame('Alice v6', $projectedAfterRecovery['name']);
        self::assertSame('alice@example.com', $projectedAfterRecovery['email']);
        self::assertSame('Street 6', $projectedAfterRecovery['address']);
    }

    private function isInfrastructureReachable(): bool
    {
        try {
            $this->readEntityManager->fetchOne('SELECT 1');
            $this->mongoStore->countEvents();
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
