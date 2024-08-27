<?php

namespace App\Command;

use App\DTO\DownloadDescription;
use App\DTO\GameDetail;
use App\DTO\OwnedItemInfo;
use App\DTO\SearchFilter;
use App\Enum\Language;
use App\Enum\MediaType;
use App\Enum\OperatingSystem;
use App\Enum\Setting;
use App\Exception\TooManyRetriesException;
use App\Service\DownloadManager;
use App\Service\FileWriter\FileWriterLocator;
use App\Service\HashCalculator;
use App\Service\Iterables;
use App\Service\OwnedItemsManager;
use App\Service\Persistence\PersistenceManager;
use App\Service\RetryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
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
        private readonly HashCalculator $hashCalculator,
        private readonly Iterables $iterables,
        private readonly RetryService $retryService,
        private readonly PersistenceManager $persistence,
        private readonly FileWriterLocator $writerLocator,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $defaultDirectory = $_ENV['DOWNLOAD_DIRECTORY']
            ?? $this->persistence->getSetting(Setting::DownloadPath)
            ?? getcwd();
        $this
            ->setDescription('Downloads all files from the local database (see update command). Can resume downloads unless --no-verify is specified.')
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'The target directory, defaults to current dir.',
                $defaultDirectory,
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
            ->addOption(
                'language',
                'l',
                InputOption::VALUE_REQUIRED,
                'Download only games for specified language. See command "languages" for list of them.',
            )
            ->addOption(
                'language-fallback-english',
                null,
                InputOption::VALUE_NONE,
                'Download english versions of games when the specified language is not found.',
            )
            ->addOption(
                'update',
                'u',
                InputOption::VALUE_NONE,
                "If you specify this flag the local database will be updated before each download and you don't need  to update it separately"
            )
            ->addOption(
                'exclude-game-with-language',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify a language to exclude. If a game supports this language, it will be skipped.',
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
                "Skip games that for whatever reason couldn't be downloaded"
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

        $noVerify = $input->getOption('no-verify');
        $operatingSystem = OperatingSystem::tryFrom($input->getOption('os') ?? '');
        $language = Language::tryFrom($input->getOption('language') ?? '');
        $englishFallback = $input->getOption('language-fallback-english');
        $excludeLanguage = Language::tryFrom($input->getOption('exclude-game-with-language') ?? '');
        $timeout = $input->getOption('idle-timeout');

        if ($language !== null && $language !== Language::English && !$englishFallback) {
            $io->warning("GOG often has multiple language versions inside the English one. Those game files will be skipped. Specify --language-fallback-english to include English versions if your language's version doesn't exist.");
        }

        if ($input->getOption('update') && $output->isVerbose()) {
            $io->info('The --update flag specified, skipping local database and downloading metadata anew');
        }

        $filter = new SearchFilter(
            operatingSystem: $operatingSystem,
            language: $language,
        );

        $iterable = $input->getOption('update')
            ? $this->iterables->map(
                $this->ownedItemsManager->getOwnedItems(MediaType::Game, $filter, httpTimeout: $timeout),
                function (OwnedItemInfo $info) use ($timeout, $output): GameDetail {
                    if ($output->isVerbose()) {
                        $output->writeln("Updating metadata for {$info->getTitle()}...");
                    }

                    return $this->ownedItemsManager->getItemDetail($info, $timeout);
                },
            )
            : $this->ownedItemsManager->getLocalGameData();

        foreach ($iterable as $game) {
            $downloads = $game->downloads;

            if ($englishFallback && $language) {
                $downloads = array_filter(
                    $game->downloads,
                    fn (DownloadDescription $download) => $download->language === $language->getLocalName()
                );
                if (!count($downloads)) {
                    $downloads = array_filter(
                        $game->downloads,
                        fn (DownloadDescription $download) => $download->language === Language::English->getLocalName(),
                    );
                }
            }
            if ($excludeLanguage) {
                foreach ($downloads as $download) {
                    if ($download->language === $excludeLanguage->getLocalName()) {
                        continue 2;
                    }
                }
            }

            foreach ($downloads as $download) {
                try {
                    $this->retryService->retry(function () use (
                        $timeout,
                        $noVerify,
                        $game,
                        $input,
                        $englishFallback,
                        $language,
                        $output,
                        $download,
                        $operatingSystem,
                        $io,
                    ) {
                        $progress = $io->createProgressBar();
                        $progress->setMessage('Starting...');
                        ProgressBar::setPlaceholderFormatterDefinition(
                            'bytes_current',
                            $this->getBytesCallable($progress->getProgress(...)),
                        );
                        ProgressBar::setPlaceholderFormatterDefinition(
                            'bytes_total',
                            $this->getBytesCallable($progress->getMaxSteps(...)),
                        );

                        $format = ' %bytes_current% / %bytes_total% [%bar%] %percent:3s%% - %message%';
                        $progress->setFormat($format);

                        if ($operatingSystem !== null && $download->platform !== $operatingSystem->value) {
                            if ($output->isVerbose()) {
                                $io->writeln("{$download->name} ({$download->platform}, {$download->language}): Skipping because of OS filter");
                            }

                            return;
                        }

                        if (
                            $language !== null
                            && $download->language !== $language->getLocalName()
                            && (!$englishFallback || $download->language !== Language::English->getLocalName())
                        ) {
                            if ($output->isVerbose()) {
                                $io->writeln("{$download->name} ({$download->platform}, {$download->language}): Skipping because of language filter");
                            }

                            return;
                        }

                        $targetDir = $this->getTargetDir($input, $game);
                        $writer = $this->writerLocator->getWriter($targetDir);
                        if (!$writer->exists($targetDir)) {
                            $writer->createDirectory($targetDir);
                        }
                        $filename = $this->downloadManager->getFilename($download, $timeout);
                        $targetFile = $writer->getFileReference("{$targetDir}/{$filename}");

                        $startAt = null;
                        if (($download->md5 || $noVerify) && $writer->exists($targetFile)) {
                            $md5 = $noVerify ? '' : $writer->getMd5Hash($targetFile);
                            if (!$noVerify && $download->md5 === $md5) {
                                if ($output->isVerbose()) {
                                    $io->writeln(
                                        "{$download->name} ({$download->platform}, {$download->language}): Skipping because it exists and is valid",
                                    );
                                }

                                return;
                            } elseif ($noVerify) {
                                if ($output->isVerbose()) {
                                    $io->writeln("{$download->name} ({$download->platform}, {$download->language}): Skipping because it exists (--no-verify specified, not checking content)");
                                }

                                return;
                            }
                            $startAt = $writer->getSize($targetFile);
                        }

                        $progress->setMaxSteps(0);
                        $progress->setProgress(0);
                        $progress->setMessage("{$download->name} ({$download->platform}, {$download->language})");

                        $responses = $this->downloadManager->download($download, function (int $current, int $total) use ($progress, $output) {
                            if ($total > 0) {
                                $progress->setMaxSteps($total);
                                $progress->setProgress($current);
                            }
                        }, $startAt, $timeout);

                        $hash = $writer->getMd5HashContext($targetFile);
                        foreach ($responses as $response) {
                            $chunk = $response->getContent();
                            $writer->writeChunk($targetFile, $chunk);
                            hash_update($hash, $chunk);
                        }
                        if (!$noVerify && $download->md5 && $download->md5 !== hash_final($hash)) {
                            $io->warning("{$download->name} ({$download->platform}, {$download->language}) failed hash check");
                        }

                        $progress->finish();
                        $io->newLine();
                    }, $input->getOption('retry'), $input->getOption('retry-delay'));
                } catch (TooManyRetriesException $e) {
                    if (!$input->getOption('skip-errors')) {
                        throw $e;
                    }
                    $io->note("{$download->name} couldn't be downloaded");
                }
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

        $title = preg_replace('@[^a-zA-Z-_0-9.]@', '_', $game->title);
        $title = preg_replace('@_{2,}@', '_', $title);

        $dir = "{$dir}/{$title}";

        return $dir;
    }

    private function getBytesCallable(callable $targetMethod): callable
    {
        return function (ProgressBar $progressBar, OutputInterface $output) use ($targetMethod) {
            $coefficient = 1;
            $unit = 'B';

            $value = $targetMethod();
            if (!$output->isVeryVerbose()) {
                if ($value > 2**10) {
                    $coefficient = 2**10;
                    $unit = 'kB';
                }
                if ($value > 2**20) {
                    $coefficient = 2**20;
                    $unit = 'MB';
                }
                if ($value > 2**30) {
                    $coefficient = 2**30;
                    $unit = 'GB';
                }
            }

            $value = $value / $coefficient;
            $value = number_format($value, 2);

            return "{$value} {$unit}";
        };
    }
}
