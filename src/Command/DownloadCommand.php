<?php

namespace App\Command;

use App\DTO\GameDetail;
use App\Enum\OperatingSystem;
use App\Service\DownloadManager;
use App\Service\OwnedItemsManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('download')]
final class DownloadCommand extends Command
{
    public function __construct(
        private readonly OwnedItemsManager $ownedItemsManager,
        private readonly DownloadManager $downloadManager,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Downloads all files from the local database (see update command). Can resume downloads unless --no-verify is specified.')
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'The target directory, defaults to current dir.',
                getcwd(),
            )
            ->addOption(
                'no-verify',
                null,
                InputOption::VALUE_NONE,
                'Set this flag to disable verification of file content before downloading. Disables resuming of downloads.'
            )
            ->addOption(
                'os',
                'o',
                InputOption::VALUE_REQUIRED,
                'Download only games for specified operating system, allowed values: ' . implode(
                    ', ',
                    array_map(
                        fn (OperatingSystem $os) => $os->value,
                        OperatingSystem::cases(),
                    )
                )
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $noVerify = $input->getOption('no-verify');
        $operatingSystem = OperatingSystem::tryFrom($input->getOption('os'));

        foreach ($this->ownedItemsManager->getLocalGameData() as $game) {
            foreach ($game->downloads as $download) {
                $progress = $io->createProgressBar();
                $progress->setFormat(
                    ' %current% MB / %max% MB [%bar%] %percent:3s%% - %message%'
                );

                $targetFile = "{$this->getTargetDir($input, $game)}/{$this->downloadManager->getFilename($download)}";
                $startAt = null;
                if (($download->md5 || $noVerify) && file_exists($targetFile)) {
                    $md5 = $noVerify ? '' : md5_file($targetFile);
                    if (!$noVerify && $download->md5 === $md5) {
                        $io->writeln(
                            "{$game->title} - {$download->name} ({$download->platform}): Skipping because it exists and is valid",
                        );
                        continue;
                    } elseif ($noVerify) {
                        $io->writeln("{$game->title} - {$download->name} ({$download->platform}): Skipping because it exists (--no-verify specified, not checking content)");
                        continue;
                    }
                    $startAt = filesize($targetFile);
                }

                if ($operatingSystem !== null && $download->platform !== $operatingSystem->value) {
                    $io->writeln("{$game->title} - {$download->name} ({$download->platform}): Skipping because of OS filter");
                    continue;
                }

                $progress->setMaxSteps(0);
                $progress->setProgress(0);
                $progress->setMessage("{$game->title} - {$download->name} ({$download->platform})");

                $responses = $this->downloadManager->download($download, function (int $current, int $total) use ($progress) {
                    if ($total > 0) {
                        $progress->setMaxSteps($total / 2**20);
                    }
                    $progress->setProgress($current / 2**20);
                }, $startAt);

                if (file_exists($targetFile)) {
                    $stream = fopen($targetFile, 'a+');
                } else {
                    $stream = fopen($targetFile, 'w+');
                }

                $hash = hash_init('md5');
                if ($startAt !== null) {
                    hash_update($hash, file_get_contents($targetFile));
                }
                foreach ($responses as $response) {
                    $chunk = $response->getContent();
                    fwrite($stream, $chunk);
                    hash_update($hash, $chunk);
                }
                if (!$noVerify && $download->md5 && $download->md5 !== hash_final($hash)) {
                    $io->warning("{$game->title} - {$download->name} failed hash check");
                }
                fclose($stream);

                $progress->finish();
                $io->newLine();
            }
        }

        return self::SUCCESS;
    }

    private function getTargetDir(InputInterface $input, GameDetail $game): string
    {
        $dir = $input->getArgument('directory');
        if (!str_starts_with($dir, '/')) {
            $dir = getcwd() . '/' . $dir;
        }

        $dir = "{$dir}/{$game->title}";
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }
}
