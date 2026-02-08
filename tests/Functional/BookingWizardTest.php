<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Domain\Model\StoredEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class BookingWizardTest extends ApiTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        
        // Ensure clean state before each test
        $this->clearDatabase();
    }

    public function testHappyPathBookingCreatesEventAndProjections(): void
    {
        $client = static::createClient();
        $bookingId = Uuid::v7()->toRfc4122();

        // 1. Submit valid booking
        $client->request('POST', '/api/booking-wizard', [
            'json' => [
                'bookingId' => $bookingId,
                'pax' => 4,
                'budget' => 150.50,
                'clientName' => 'John TED Talk',
                'clientEmail' => 'john@ted.com',
            ],
        ]);

        self::assertResponseIsSuccessful();

        // 2. Check Event Store
        $storedEvent = $this->entityManager->getRepository(StoredEvent::class)
            ->findOneBy(['aggregateId' => $bookingId]);
        
        self::assertNotNull($storedEvent, 'Event should be in the store');
        self::assertEquals('App\Domain\Event\BookingWizardCompleted', $storedEvent->eventType);

        // 3. Check Projections (Wait a bit for async if it were async, but here it is sync by default in test)
        $user = $this->entityManager->getConnection()
            ->fetchAssociative('SELECT * FROM users WHERE email = :email', ['email' => 'john@ted.com']);
        self::assertNotFalse($user, 'User projection should have created the user');

        $booking = $this->entityManager->getConnection()
            ->fetchAssociative('SELECT * FROM bookings WHERE id = :id', ['id' => $bookingId]);
        self::assertNotFalse($booking, 'Booking projection should have created the booking');
    }

    public function testIdempotencyWithSameId(): void
    {
        $client = static::createClient();
        $bookingId = Uuid::v7()->toRfc4122();
        $payload = [
            'bookingId' => $bookingId,
            'pax' => 2,
            'budget' => 100,
            'clientName' => 'Duplicate User',
            'clientEmail' => 'dup@example.com',
        ];

        // First attempt
        $client->request('POST', '/api/booking-wizard', ['json' => $payload]);
        self::assertResponseIsSuccessful();

        // Second attempt with same ID
        $client->request('POST', '/api/booking-wizard', ['json' => $payload]);
        self::assertResponseIsSuccessful(); // Should be idempotent (return success but do nothing)

        // Check only 1 event exists
        $events = $this->entityManager->getRepository(StoredEvent::class)->findBy(['aggregateId' => $bookingId]);
        self::assertCount(1, $events, 'There should be exactly one event for the same aggregate ID');
    }

    public function testInvalidDataReturns422(): void
    {
        $client = static::createClient();

        // Missing email and negative budget
        $client->request('POST', '/api/booking-wizard', [
            'json' => [
                'bookingId' => Uuid::v7()->toRfc4122(),
                'pax' => 0,
                'budget' => -10,
                'clientName' => 'Invalid User',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testSameEmailMultipleBookingsOnlyOneUserCreated(): void
    {
        $client = static::createClient();
        $email = 'repeat@example.com';

        // Booking 1
        $client->request('POST', '/api/booking-wizard', [
            'json' => [
                'bookingId' => Uuid::v7()->toRfc4122(),
                'pax' => 2,
                'budget' => 50,
                'clientName' => 'Repeat User',
                'clientEmail' => $email,
            ],
        ]);

        // Booking 2
        $client->request('POST', '/api/booking-wizard', [
            'json' => [
                'bookingId' => Uuid::v7()->toRfc4122(),
                'pax' => 5,
                'budget' => 200,
                'clientName' => 'Repeat User',
                'clientEmail' => $email,
            ],
        ]);

        // Check users table
        $users = $this->entityManager->getConnection()
            ->fetchAllAssociative('SELECT * FROM users WHERE email = :email', ['email' => $email]);
        
        self::assertCount(1, $users, 'Only one user record should exist for the same email');
        
        // Check bookings table
        $bookings = $this->entityManager->getConnection()->fetchAllAssociative('SELECT * FROM bookings');
        self::assertCount(2, $bookings, 'Two bookings should exist');
    }

    public function testSystemRebuildFromEvents(): void
    {
        $client = static::createClient();
        
        // 1. Create a booking
        $bookingId = Uuid::v7()->toRfc4122();
        $client->request('POST', '/api/booking-wizard', [
            'json' => [
                'bookingId' => $bookingId,
                'pax' => 10,
                'budget' => 500,
                'clientName' => 'Rebuild Test',
                'clientEmail' => 'rebuild@test.com',
            ],
        ]);

        // 2. Simulate "system failure" by clearing read models manually
        $this->entityManager->getConnection()->executeStatement('TRUNCATE users CASCADE');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE bookings CASCADE');

        $user = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM users');
        self::assertEquals(0, $user);

        // 3. Run Rebuild Command
        $kernel = self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        $command = $application->find('app:projections:rebuild');
        $commandTester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $commandTester->execute([]);

        // 4. Verify data is back
        $userCount = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM users');
        self::assertEquals(1, $userCount, 'User should have been restored');
        
        $bookingCount = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM bookings');
        self::assertEquals(1, $bookingCount, 'Booking should have been restored');
    }

    private function clearDatabase(): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('TRUNCATE event_store CASCADE');
        $conn->executeStatement('TRUNCATE users CASCADE');
        $conn->executeStatement('TRUNCATE bookings CASCADE');
    }
}
