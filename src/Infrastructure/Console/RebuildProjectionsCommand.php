<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Domain\Model\StoredEvent;
use App\Infrastructure\Persistence\Doctrine\ReadEntityManager;
use App\Infrastructure\Persistence\Mongo\MongoStore;
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
        private MongoStore $mongoStore,
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
        $io->section('Clearing Read Models & Checkpoints...');
        $this->readEntityManager->fetchOne('TRUNCATE users CASCADE');
        $this->readEntityManager->fetchOne('TRUNCATE bookings CASCADE');
        $this->mongoStore->clearCheckpoints();
        $io->success('Read models and checkpoints cleared.');

        // 2. Fetch Events from Mongo
        $io->section('Replaying Events from MongoDB...');
        $events = $this->mongoStore->findEvents();

        foreach ($events as $storedEvent) {
            $io->text(sprintf('Processing: %s', $storedEvent->eventType));
            
            $event = $this->serializer->deserialize(
                json_encode($storedEvent->payload),
                $storedEvent->eventType,
                'json'
            );

            $this->eventBus->dispatch($event);
        }

        $io->success(sprintf('Rebuild complete. Processed %d events.', count($events)));

        return Command::SUCCESS;
    }
}