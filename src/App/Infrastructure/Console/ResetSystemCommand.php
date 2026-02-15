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
    name: 'app:system:reset',
    description: 'Resets the entire system state (SQL Read Models + MongoDB Event Store) and reloads fixtures.',
)]
final class ResetSystemCommand extends Command
{
    public function __construct(
        private ArchitectureControlService $architectureService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Resetting System State');

        try {
            $this->architectureService->reset();
            $io->success('System has been fully reset and fixtures reloaded.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to reset system: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
