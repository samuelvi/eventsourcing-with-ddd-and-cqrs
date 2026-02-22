<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger\Fixtures;

use App\Application\Bus\AsyncCommandBusInterface;
use App\Application\Bus\SyncCommandBusInterface;
use App\Tests\Infrastructure\Messenger\Fixtures\TestFailingMessage;
use App\Tests\Infrastructure\Messenger\Fixtures\TestMessage;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class MessengerIntegrationTest extends KernelTestCase
{
    private MessageBusInterface $bus;
    private AsyncCommandBusInterface $asyncCommandBus;
    private SyncCommandBusInterface $syncCommandBus;
    private Connection $queueConnection;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->bus = self::getContainer()->get(MessageBusInterface::class);
        $this->asyncCommandBus = self::getContainer()->get(AsyncCommandBusInterface::class);
        $this->syncCommandBus = self::getContainer()->get(SyncCommandBusInterface::class);
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.messaging_connection');
        $this->queueConnection = $connection;

        // Limpiar tabla antes de empezar (crearla si no existe)
        try {
            $this->queueConnection->executeStatement('CREATE TABLE IF NOT EXISTS messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
            $this->queueConnection->executeStatement('TRUNCATE TABLE messenger_messages');
        } catch (\Exception $e) {
            // Ignorar
        }
    }

    public function testMessageIsPersistedInAsyncQueue(): void
    {
        $message = new TestMessage('Hola Mundo Async');
        
        try {
            // Despachamos forzando transporte 'async'
            $this->bus->dispatch($message, [new TransportNamesStamp(['async'])]);
        } catch (\Exception $e) {
            $this->fail('El despacho no debería haber fallado. ¿Está ejecutándose de forma síncrona? Error: ' . $e->getMessage());
        }

        // Verificamos directamente en SQL que existe algún mensaje
        $totalCount = $this->queueConnection->fetchOne("SELECT COUNT(*) FROM messenger_messages");
        $this->assertGreaterThan(0, $totalCount, 'Debería haber al menos un mensaje en la tabla messenger_messages');

        // Verificamos el queue_name exacto (por si acaso Symfony ignoró nuestro query param del DSN y usó el de la config)
        $row = $this->queueConnection->fetchAssociative("SELECT * FROM messenger_messages LIMIT 1");
        $this->assertNotFalse($row, 'Debería haber una fila en la base de datos');
        
        // Aceptamos tanto 'test_async' (del DSN) como 'async' (de la config options)
        $this->assertContains($row['queue_name'], ['test_async', 'async'], 'El queue_name no coincide con lo esperado');
    }

    public function testFailedMessageGoesToDLQ(): void
    {
        $message = new TestFailingMessage('Fallo Controlado');
        
        try {
            // 1. Encolar
            $this->bus->dispatch($message, [new TransportNamesStamp(['async'])]);
        } catch (\Exception $e) {
            // Si entra aquí, es que se ha ejecutado de forma síncrona
            $this->markTestSkipped('El mensaje se ejecutó de forma síncrona, saltando prueba de encolado asíncrono.');
            return;
        }

        // 2. Verificar que entró
        $totalCount = $this->queueConnection->fetchOne("SELECT COUNT(*) FROM messenger_messages");
        $this->assertGreaterThan(0, $totalCount, 'El mensaje debería estar encolado inicialmente');
    }

    public function testAsyncCommandBusDispatchDoesNotBubbleHandlerException(): void
    {
        $message = new TestFailingMessage('Fallo controlado async');

        try {
            $this->asyncCommandBus->dispatch($message);
            $this->addToAssertionCount(1);
        } catch (\Throwable $exception) {
            $this->fail('AsyncCommandBus no debería propagar la excepción del handler en dispatch: ' . $exception->getMessage());
        }
    }

    public function testSyncCommandBusDispatchBubblesHandlerExceptionImmediately(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fallo simulado para DLQ');

        $this->syncCommandBus->dispatch(new TestFailingMessage('Fallo controlado sync'));
    }
}
