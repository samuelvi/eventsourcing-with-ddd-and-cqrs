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
    private const CACHE_KEY_MASTER = 'demo_projections_enabled';
    private const CACHE_KEY_USER_PROJECTIONS = 'demo_user_projections_enabled';
    private const CACHE_KEY_BOOKING_PROJECTIONS = 'demo_booking_projections_enabled';

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
        $masterEnabled = $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $userEnabled = $this->cache->get(self::CACHE_KEY_USER_PROJECTIONS, fn() => true);
        $bookingEnabled = $this->cache->get(self::CACHE_KEY_BOOKING_PROJECTIONS, fn() => true);
        
        return new JsonResponse([
            'projectionsEnabled' => $masterEnabled,
            'userProjectionsEnabled' => $userEnabled,
            'bookingProjectionsEnabled' => $bookingEnabled
        ]);
    }

    #[Route('/api/demo/toggle/{type}', methods: ['POST'])]
    public function toggle(string $type): Response
    {
        $key = match($type) {
            'master' => self::CACHE_KEY_MASTER,
            'user' => self::CACHE_KEY_USER_PROJECTIONS,
            'booking' => self::CACHE_KEY_BOOKING_PROJECTIONS,
            default => throw new \InvalidArgumentException('Invalid type')
        };
        
        $current = $this->cache->get($key, fn() => true);
        $newValue = !$current;

        $this->cache->delete($key);
        $this->cache->get($key, fn() => $newValue);

        return new JsonResponse([
            ($type === 'master' ? 'projectionsEnabled' : ($type . 'ProjectionsEnabled')) => $newValue
        ]);
    }

    #[Route('/api/demo/rebuild', methods: ['POST'])]
    public function rebuild(): Response
    {
        // 1. Force both toggles back to enabled for the rebuild process
        $this->cache->delete(self::CACHE_KEY_MASTER);
        $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $this->cache->delete(self::CACHE_KEY_USER_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_USER_PROJECTIONS, fn() => true);
        $this->cache->delete(self::CACHE_KEY_BOOKING_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_BOOKING_PROJECTIONS, fn() => true);

        // 2. Clear tables
        $this->readEntityManager->fetchOne('TRUNCATE users CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE bookings CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE projection_checkpoints CASCADE');

        // 3. Fetch all events
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

        // Get checkpoints for display
        $checkpoints = $this->readEntityManager->query('SELECT projection_name, last_event_id FROM projection_checkpoints');
        $checkpointsMap = [];
        foreach ($checkpoints as $cp) {
            $checkpointsMap[$cp['projection_name']] = $cp['last_event_id'];
        }

        return new JsonResponse([
            'events' => (int)$eventCount,
            'users' => (int)$userCount,
            'bookings' => (int)$bookingCount,
            'checkpoints' => $checkpointsMap
        ]);
    }

    #[Route('/api/demo/reset', methods: ['POST'])]
    public function reset(): Response
    {
        $this->cache->delete(self::CACHE_KEY_MASTER);
        $this->cache->get(self::CACHE_KEY_MASTER, fn() => true);
        $this->cache->delete(self::CACHE_KEY_USER_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_USER_PROJECTIONS, fn() => true);
        $this->cache->delete(self::CACHE_KEY_BOOKING_PROJECTIONS);
        $this->cache->get(self::CACHE_KEY_BOOKING_PROJECTIONS, fn() => true);

        // Brutal Clean (Truncate ALL tables in the correct order)
        $this->readEntityManager->fetchOne('TRUNCATE users CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE bookings CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE event_store CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE projection_checkpoints CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE products CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE suppliers CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE menus CASCADE');

        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($this->kernel);
        $application->setAutoExit(false);

        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--no-interaction' => true,
        ]);

        $application->run($input, new \Symfony\Component\Console\Output\NullOutput());

        return new JsonResponse(['status' => 'success']);
    }
}
