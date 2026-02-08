<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Domain\Model\StoredEvent;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Doctrine\WriteEntityManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand(
    name: 'app:projections:rebuild',
    description: 'Rebuilds read model projections from the event store',
)]
final class RebuildProjectionsCommand extends Command
{
    public function __construct(
        private ReadEntityManager $readEntityManager,
        private WriteEntityManager $writeEntityManager,
        private MessageBusInterface $eventBus,
        private SerializerInterface $serializer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Rebuilding Projections');

        // 1. Clear Read Models
        $io->section('Clearing Read Models...');
        // We use raw SQL to truncate to avoid ORM overhead and issues with foreign keys if any
        $this->readEntityManager->fetchOne('TRUNCATE users CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE bookings CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE projection_checkpoints CASCADE');
        $io->success('Read models and checkpoints cleared.');

        // 2. Fetch Events
        $io->section('Replaying Events...');
        $events = $this->writeEntityManager->getRepository(StoredEvent::class)->findBy([], ['occurredOn' => 'ASC']);

        foreach ($events as $storedEvent) {
            $io->text(sprintf('Processing: %s', $storedEvent->eventType));
            
            // Deserialize payload back to Domain Event
            $event = $this->serializer->deserialize(
                json_encode($storedEvent->payload),
                $storedEvent->eventType,
                'json'
            );

            // Dispatch to Bus (Projections will handle it)
            // Note: In a real app, we might want a specific 'replay' bus to avoid triggering emails etc.
            $this->eventBus->dispatch($event);
        }

        $io->success(sprintf('Rebuild complete. Processed %d events.', count($events)));

        return Command::SUCCESS;
    }
}
