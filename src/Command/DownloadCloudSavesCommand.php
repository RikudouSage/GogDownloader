<?php

namespace App\Command;

use App\DTO\GameDetail;
use App\DTO\OwnedItemInfo;
use App\Enum\MediaType;
use App\Enum\NamingConvention;
use App\Enum\Setting;
use App\Service\CloudSavesManager;
use App\Service\FileWriter\FileWriterLocator;
use App\Service\Iterables;
use App\Service\OwnedItemsManager;
use App\Service\Persistence\PersistenceManager;
use App\Service\RetryService;
use App\Trait\MigrationCheckerTrait;
use App\Trait\TargetDirectoryTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[AsCommand('download-saves', description: 'Download cloud saves for your games', aliases: ['saves'])]
final class DownloadCloudSavesCommand extends Command
{
    use TargetDirectoryTrait;
    use MigrationCheckerTrait;

    public function __construct(
        private readonly CloudSavesManager $cloudSaves,
        private readonly PersistenceManager $persistence,
        private readonly OwnedItemsManager $ownedItemsManager,
        private readonly Iterables $iterables,
        private readonly RetryService $retryService,
        private readonly FileWriterLocator $writerLocator,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'The target directory.',
            )
            ->addOption(
                'no-verify',
                null,
                InputOption::VALUE_NONE,
                'Set this flag to disable verification of file content before downloading. Disables resuming of downloads.'
            )
            ->addOption(
                'update',
                'u',
                InputOption::VALUE_NONE,
                "If you specify this flag the local database will be updated before each download and you don't need to update it separately"
            )
            ->addOption(
                'retry',
                null,
                InputOption::VALUE_REQUIRED,
                'How many times should the download be retried in case of failure.',
                3,
            )
            ->addOption(
                'retry-delay',
                null,
                InputOption::VALUE_REQUIRED,
                'The delay in seconds between each retry.',
                1,
            )
            ->addOption(
                'skip-errors',
                null,
                InputOption::VALUE_NONE,
                "Skip saves that for whatever reason couldn't be downloaded"
            )
            ->addOption(
                'idle-timeout',
                null,
                InputOption::VALUE_REQUIRED,
                'Set the idle timeout in seconds for http requests',
                3,
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->showInfoIfMigrationsAreNeeded($io, $this->persistence);

        if ($this->persistence->getSetting(Setting::NamingConvention) === NamingConvention::Custom->value) {
            $io->warning("You're using the deprecated custom naming convention for game directories. To migrate your game directory to the new naming convention, please use the command 'migrate-naming-scheme'.");
        }

        $noVerify = $input->getOption('no-verify');
        $update = $input->getOption('update');
        $retryCount = $input->getOption('retry');
        $retryDelay = $input->getOption('retry-delay');
        $skipErrors = $input->getOption('skip-errors');
        $timeout = $input->getOption('idle-timeout');

        $games = $input->getOption('update')
            ? $this->iterables->map(
                $this->ownedItemsManager->getOwnedItems(MediaType::Game, httpTimeout: $timeout),
                function (OwnedItemInfo $info) use ($timeout, $output): GameDetail {
                    if ($output->isVerbose()) {
                        $output->writeln("Updating metadata for {$info->getTitle()}...");
                    }

                    return $this->ownedItemsManager->getItemDetail($info, $timeout);
                },
            )
            : $this->ownedItemsManager->getLocalGameData();

        foreach ($games as $game) {
            if (!$this->cloudSaves->supports($game)) {
                continue;
            }

            try {
                $this->retryService->retry(function () use ($noVerify, $input, $io, $game) {
                    $progress = $io->createProgressBar();
                    $format = ' %current% / %max% [%bar%] %percent:3s%% - %message%';
                    $progress->setFormat($format);
                    $progress->setMessage("[{$game->title}] Fetching file list");
                    $saves = $this->cloudSaves->getGameSaves($game);
                    $progress->setMaxSteps(count($saves));

                    if (!count($saves)) {
                        if ($io->isVerbose()) {
                            $io->writeln("[{$game->title}] Skipping, because no save files were found");
                        }

                        return;
                    }

                    $targetDirectory = $this->getTargetDir($input, $game, 'SaveFiles');
                    $writer = $this->writerLocator->getWriter($targetDirectory);
                    if (!$writer->exists($targetDirectory)) {
                        $writer->createDirectory($targetDirectory);
                    }

                    foreach ($saves as $save) {
                        $progress->setMessage("[{$game->title}] Downloading {$save->name}");

                        $filename = "{$targetDirectory}/{$save->name}";
                        if (!$writer->exists(dirname($filename))) {
                            $writer->createDirectory(dirname($filename));
                        }
                        $targetFile = $writer->getFileReference($filename);

                        if (!$noVerify && $writer->exists($targetFile)) {
                            $calculatedMd5 = $writer->getMd5Hash($targetFile);
                            $calculatedMd5 = $this->persistence->getCompressedHash($calculatedMd5) ?? $calculatedMd5;

                            if ($calculatedMd5 === $save->hash) {
                                if ($io->isVerbose()) {
                                    $io->writeln("[{$game->title}] ({$save->name}): Skipping because it exists and is valid", );
                                }
                                $progress->advance();
                                continue;
                            }
                        }

                        if ($writer->exists($targetFile)) {
                            $writer->remove($targetFile);
                        }

                        $content = $this->cloudSaves->downloadSave($save, $game);

                        if (!$noVerify && $save->hash && $save->hash !== $content->hash) {
                            $io->warning("[{$game->title}] {$save->name} failed hash check");
                        } elseif (!$noVerify) {
                            $this->persistence->storeUncompressedHash($content->hash, md5($content->content));
                        }

                        $writer->writeChunk($targetFile, $content->content);
                        $writer->finalizeWriting($targetFile, $content->hash);

                        $progress->advance();
                    }

                    $progress->finish();
                    $io->writeln('');
                }, $retryCount, $retryDelay);
            } catch (Throwable $e) {
                if ($skipErrors) {
                    if ($output->isVerbose()) {
                        $io->comment("[{$game->title}] Skipping the game because there were errors and --skip-errors is enabled");
                    }
                    continue;
                }

                throw $e;
            }
        }

        $io->success('All configured save files have been downloaded.');

        return Command::SUCCESS;
    }
}
