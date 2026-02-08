<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use App\Domain\Model\StoredEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class DemoControlController extends AbstractController
{
    private const CACHE_KEY = 'demo_projections_enabled';

    public function __construct(
        private CacheInterface $cache,
        private ReadEntityManager $readEntityManager,
        private WriteEntityManager $writeEntityManager,
        private MessageBusInterface $eventBus,
        private SerializerInterface $serializer,
    ) {}

    #[Route('/api/demo/status', methods: ['GET'])]
    public function getStatus(): Response
    {
        $enabled = $this->cache->get(self::CACHE_KEY, fn(ItemInterface $item) => true);
        
        return new JsonResponse(['projectionsEnabled' => $enabled]);
    }

    #[Route('/api/demo/toggle', methods: ['POST'])]
    public function toggle(): Response
    {
        $this->cache->delete(self::CACHE_KEY);
        $newValue = !(bool)$this->cache->get(self::CACHE_KEY, fn(ItemInterface $item) => false);
        
        // Save the new value
        $this->cache->get(self::CACHE_KEY, function(ItemInterface $item) use ($newValue) {
            return $newValue;
        });

        // We must force the value because get() with callback only sets if it doesn't exist
        // A simpler way for a demo:
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->get(self::CACHE_KEY, fn() => $newValue);

        return new JsonResponse(['projectionsEnabled' => $newValue]);
    }

    #[Route('/api/demo/rebuild', methods: ['POST'])]
    public function rebuild(): Response
    {
        // Logic identical to the CLI command
        $this->readEntityManager->fetchOne('TRUNCATE users CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE bookings CASCADE');

        $events = $this->writeEntityManager->getRepository(StoredEvent::class)->findBy([], ['occurredOn' => 'ASC']);

        foreach ($events as $storedEvent) {
            $event = $this->serializer->deserialize(
                json_encode($storedEvent->payload),
                $storedEvent->eventType,
                'json'
            );
            $this->eventBus->dispatch($event);
        }

        return new JsonResponse(['status' => 'success', 'processed' => count($events)]);
    }

    #[Route('/api/demo/stats', methods: ['GET'])]
    public function getStats(): Response
    {
        $eventCount = $this->readEntityManager->fetchOne('SELECT COUNT(*) FROM event_store')['count'];
        $userCount = $this->readEntityManager->fetchOne('SELECT COUNT(*) FROM users')['count'];
        $bookingCount = $this->readEntityManager->fetchOne('SELECT COUNT(*) FROM bookings')['count'];

        return new JsonResponse([
            'events' => (int)$eventCount,
            'users' => (int)$userCount,
            'bookings' => (int)$bookingCount
        ]);
    }

    #[Route('/api/demo/reset', methods: ['POST'])]
    public function reset(): Response
    {
        // 1. Force projections back to enabled
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->get(self::CACHE_KEY, fn() => true);

        // 2. Execute doctrine:fixtures:load
        $kernel = $this->container->get('kernel');
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
        ]);

        $application->run($input, new \Symfony\Component\Console\Output\NullOutput());

        return new JsonResponse(['status' => 'success']);
    }
}
