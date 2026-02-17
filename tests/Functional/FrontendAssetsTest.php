<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FrontendAssetsTest extends WebTestCase
{
    public function testHomeUsesBuiltFrontendAssetsInTestEnvironment(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('/build/assets/app-', $html);
        self::assertStringNotContainsString('app.tsx', $html);
        self::assertStringNotContainsString(':5173', $html);
    }
}
