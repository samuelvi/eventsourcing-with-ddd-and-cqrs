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

        // 2. Check Event Store via API
        $response = $client->request('GET', '/api/event-store');
        $events = $response->toArray()['hydra:member'];
        $hasEvent = count(array_filter($events, fn($e) => ($e['aggregateId'] ?? '') === $bookingId)) > 0;
        self::assertTrue($hasEvent, 'Event should be in the store (verified via API)');

        // 3. Check Projections via API
        $response = $client->request('GET', '/api/users');
        $users = $response->toArray()['hydra:member'];
        $hasUser = count(array_filter($users, fn($u) => ($u['email'] ?? '') === 'john@ted.com')) > 0;
        self::assertTrue($hasUser, 'User projection should have created the user (verified via API)');

        $response = $client->request('GET', '/api/bookings');
        $bookings = $response->toArray()['hydra:member'];
        $hasBooking = count(array_filter($bookings, fn($b) => ($b['id'] ?? '') === $bookingId)) > 0;
        self::assertTrue($hasBooking, 'Booking projection should have created the booking (verified via API)');
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
        self::assertResponseIsSuccessful(); // Should be idempotent

        // Check only 1 event exists via API
        $response = $client->request('GET', '/api/event-store');
        $events = $response->toArray()['hydra:member'];
        $relevantEvents = array_filter($events, fn($e) => ($e['aggregateId'] ?? '') === $bookingId);
        self::assertCount(1, $relevantEvents, 'There should be exactly one event for the same aggregate ID (verified via API)');
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

        // Check users table via API
        $response = $client->request('GET', '/api/users');
        $users = array_filter($response->toArray()['hydra:member'], fn($u) => $u['email'] === $email);
        self::assertCount(1, $users, 'Only one user record should exist for the same email (verified via API)');
        
        // Check bookings table via API
        $response = $client->request('GET', '/api/bookings');
        self::assertCount(2, $response->toArray()['hydra:member'], 'Two bookings should exist (verified via API)');
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
        // We still use DB for clearing because it's a "destructive" action for the test setup
        $this->entityManager->getConnection()->executeStatement('TRUNCATE users CASCADE');
        $this->entityManager->getConnection()->executeStatement('TRUNCATE bookings CASCADE');

        // Verify cleared via API
        $response = $client->request('GET', '/api/users');
        self::assertCount(0, $response->toArray()['hydra:member']);

        // 3. Run Rebuild Command
        $kernel = self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        $command = $application->find('app:projections:rebuild');
        $commandTester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $commandTester->execute([]);

        // 4. Verify data is back via API
        $response = $client->request('GET', '/api/users');
        self::assertCount(1, $response->toArray()['hydra:member'], 'User should have been restored (verified via API)');
        
        $response = $client->request('GET', '/api/bookings');
        self::assertCount(1, $response->toArray()['hydra:member'], 'Booking should have been restored (verified via API)');
    }

    private function clearDatabase(): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('TRUNCATE users CASCADE');
        $conn->executeStatement('TRUNCATE bookings CASCADE');

        // Clear Mongo
        $mongoStore = self::getContainer()->get(\App\Infrastructure\Persistence\Mongo\MongoStore::class);
        $mongoStore->clearAll();
    }
}