<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Application\Service\ArchitectureControlService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:projections:rebuild',
    description: 'Rebuilds read model projections from the event store',
)]
final class RebuildProjectionsCommand extends Command
{
    public function __construct(
        private ArchitectureControlService $architectureService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Rebuilding Projections');

        $io->section('Running Rebuild Process...');
        $count = $this->architectureService->rebuild();

        $io->success(sprintf('Rebuild complete. Processed %d events.', $count));

        return Command::SUCCESS;
    }
}