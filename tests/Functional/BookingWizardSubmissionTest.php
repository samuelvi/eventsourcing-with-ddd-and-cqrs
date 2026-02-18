<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class BookingWizardSubmissionTest extends WebTestCase
{
    public function testValidBookingWizardSubmissionIsAcceptedAndStored(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/test/reset-db-empty');
        self::assertResponseIsSuccessful();

        $client->request(
            'POST',
            '/api/booking-wizard',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'bookingId' => Uuid::v7()->toRfc4122(),
                'pax' => 2,
                'budget' => 120,
                'clientName' => 'Wizard Happy Path',
                'clientEmail' => 'wizard-happy-path@test.com',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(202);

        $client->request('GET', '/api/event-store');
        self::assertResponseIsSuccessful();

        $payload = (array) json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $payload['hydra:totalItems'] ?? null);
    }
}
