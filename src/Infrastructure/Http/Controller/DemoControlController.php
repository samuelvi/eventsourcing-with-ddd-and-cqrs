<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use App\Domain\Model\StoredEvent;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class DemoControlController extends AbstractController
{
    private const CACHE_KEY_PROJECTIONS = 'demo_projections_enabled';
    private const CACHE_KEY_EVENT_STORE = 'demo_event_store_enabled';

    public function __construct(
        private CacheInterface $cache,
        private ReadEntityManager $readEntityManager,
        private WriteEntityManager $writeEntityManager,
        private MessageBusInterface $eventBus,
        private SerializerInterface $serializer,
        private KernelInterface $kernel,
    ) {}

    #[Route('/api/demo/status', methods: ['GET'])]
    public function getStatus(): Response
    {
        $projections = $this->cache->get(self::CACHE_KEY_PROJECTIONS, fn() => true);
        $eventStore = $this->cache->get(self::CACHE_KEY_EVENT_STORE, fn() => true);
        
        return new JsonResponse([
            'projectionsEnabled' => $projections,
            'eventStoreEnabled' => $eventStore
        ]);
    }

    #[Route('/api/demo/toggle', methods: ['POST'])]
    public function toggle(): Response
    {
        $this->cache->delete(self::CACHE_KEY_PROJECTIONS);
        $newValue = !(bool)$this->cache->get(self::CACHE_KEY_PROJECTIONS, fn() => false);
        $this->cache->delete(self::CACHE_KEY_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_PROJECTIONS, fn() => $newValue);

        return new JsonResponse(['projectionsEnabled' => $newValue]);
    }

    #[Route('/api/demo/toggle-event-store', methods: ['POST'])]
    public function toggleEventStore(): Response
    {
        $this->cache->delete(self::CACHE_KEY_EVENT_STORE);
        $newValue = !(bool)$this->cache->get(self::CACHE_KEY_EVENT_STORE, fn() => false);
        $this->cache->delete(self::CACHE_KEY_EVENT_STORE);
        $this->cache->get(self::CACHE_KEY_EVENT_STORE, fn() => $newValue);

        return new JsonResponse(['eventStoreEnabled' => $newValue]);
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
        // 1. Force everything back to enabled
        $this->cache->delete(self::CACHE_KEY_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_PROJECTIONS, fn() => true);
        $this->cache->delete(self::CACHE_KEY_EVENT_STORE);
        $this->cache->get(self::CACHE_KEY_EVENT_STORE, fn() => true);

        // 2. Brutal Clean (Truncate all tables to be 100% sure)
        $this->readEntityManager->fetchOne('TRUNCATE users CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE bookings CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE event_store CASCADE');

        // 3. Execute doctrine:fixtures:load programmatically
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($this->kernel);
        $application->setAutoExit(false);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
            '--append' => true, // We already truncated manually, so append is safer/faster
        ]);

        $application->run($input, new \Symfony\Component\Console\Output\NullOutput());

        return new JsonResponse(['status' => 'success']);
    }
}
