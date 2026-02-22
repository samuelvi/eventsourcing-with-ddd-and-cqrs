<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Controller;

use App\Application\Bus\AsyncCommandBusInterface;
use App\Infrastructure\Http\Controller\BookingWizardController;
use PHPUnit\Framework\TestCase;

final class BookingWizardControllerIntegrationTest extends TestCase
{
    public function testBookingWizardControllerUsesAsyncCommandBusContract(): void
    {
        $method = new \ReflectionMethod(BookingWizardController::class, '__invoke');
        $parameters = $method->getParameters();
        $commandBusParameter = $parameters[1] ?? null;

        self::assertNotNull($commandBusParameter, 'El segundo parámetro del controller debe ser command bus.');

        $type = $commandBusParameter->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(
            AsyncCommandBusInterface::class,
            $type->getName(),
            'BookingWizardController debe depender de AsyncCommandBusInterface.'
        );
    }
}
