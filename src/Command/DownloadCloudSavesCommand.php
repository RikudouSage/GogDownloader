<?php

namespace App\Command;

use App\Service\CloudSavesManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('download-saves', description: 'Download cloud saves for your games', aliases: ['saves'])]
final class DownloadCloudSavesCommand extends Command
{
    public function __construct(
        private readonly CloudSavesManager $cloudSaves,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cloudSaves->list();

        return Command::SUCCESS;
    }
}
