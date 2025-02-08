<?php

namespace App\Command;

use App\Enum\Language;
use App\Enum\Setting;
use App\Exception\ExitException;
use App\Exception\TooManyRetriesException;
use App\Exception\UnreadableFileException;
use App\Service\DownloadManager;
use App\Service\FileWriter\FileWriterLocator;
use App\Service\Iterables;
use App\Service\OwnedItemsManager;
use App\Service\Persistence\PersistenceManager;
use App\Service\RetryService;
use App\Trait\CommonOptionsTrait;
use App\Trait\EnumExceptionParserTrait;
use App\Trait\FilteredGamesResolverTrait;
use App\Trait\TargetDirectoryTrait;
use InvalidArgumentException;
use RuntimeException;
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
    use TargetDirectoryTrait;
    use EnumExceptionParserTrait;
    use CommonOptionsTrait;
    use FilteredGamesResolverTrait;

    private bool $canKillSafely = true;

    private bool $exitRequested = false;

    public function __construct(
        private readonly OwnedItemsManager $ownedItemsManager,
        private readonly DownloadManager $downloadManager,
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
                'The target directory.',
                $defaultDirectory,
            )
            ->addOption(
                'no-verify',
                null,
                InputOption::VALUE_NONE,
                'Set this flag to disable verification of file content before downloading. Disables resuming of downloads.'
            )
            ->addOsFilterOption()
            ->addLanguageFilterOption()
            ->addLanguageFallbackEnglishOption()
            ->addUpdateOption()
            ->addExcludeLanguageOption()
            ->addHttpRetryOption()
            ->addHttpRetryDelayOption()
            ->addSkipHttpErrorsOption()
            ->addHttpIdleTimeoutOption()
            ->addOption(
                name: 'chunk-size',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The chunk size in MB. Some file providers support sending parts of a file, this options sets the size of a single part. Cannot be lower than 5',
                default: 10,
            )
            ->addGameNameFilterOption()
            ->addExcludeGameNameFilterOption()
            ->addOption(
                name: 'bandwidth',
                shortcut: 'b',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Specify the maximum download speed in bytes. You can use the k postfix for kilobytes or m postfix for megabytes (for example 200k or 4m to mean 200 kilobytes and 4 megabytes respectively)',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->handleSignals($io);

            $chunkSize = $input->getOption('chunk-size') * 1024 * 1024;
            if ($chunkSize < 5 * 1024 * 1024) {
                $io->error('The chunk size cannot be lower than 5 MB.');

                return self::FAILURE;
            }
            $this->dispatchSignals();

            $englishFallback = $input->getOption('language-fallback-english');
            $languages = $this->getLanguages($input);
            if ($languages && !in_array(Language::English, $languages, true) && !$englishFallback) {
                $io->warning("GOG often has multiple language versions inside the English one. Those game files will be skipped. Specify --language-fallback-english to include English versions if your language's version doesn't exist.");
            }

            if ($input->getOption('update') && $output->isVerbose()) {
                $io->info('The --update flag specified, skipping local database and downloading metadata anew');
            }

            $timeout = $input->getOption('idle-timeout');
            $noVerify = $input->getOption('no-verify');
            $iterable = $this->getGames($input, $output, $this->ownedItemsManager);

            $this->dispatchSignals();
            foreach ($iterable as $game) {
                $downloads = $game->downloads;

                foreach ($downloads as $download) {
                    try {
                        $this->retryService->retry(function () use (
                            $chunkSize,
                            $timeout,
                            $noVerify,
                            $game,
                            $input,
                            $englishFallback,
                            $output,
                            $download,
                            $io,
                        ) {
                            $this->canKillSafely = false;
                            $this->dispatchSignals();
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

                            $targetDir = $this->getTargetDir($input, $game);
                            $writer = $this->writerLocator->getWriter($targetDir);
                            if (!$writer->exists($targetDir)) {
                                $writer->createDirectory($targetDir);
                            }
                            $filename = $this->downloadManager->getFilename($download, $timeout);
                            if (!$filename) {
                                throw new RuntimeException("{$download->name} ({$download->platform}, {$download->language}): Failed getting the filename for {$download->name}");
                            }
                            $targetFile = $writer->getFileReference("{$targetDir}/{$filename}");

                            $startAt = null;
                            if (($download->md5 || $noVerify) && $writer->exists($targetFile)) {
                                try {
                                    $md5 = $noVerify ? '' : $writer->getMd5Hash($targetFile);
                                } catch (UnreadableFileException) {
                                    $io->warning("{$download->name} ({$download->platform}, {$download->language}): Tried to get existing hash of {$download->name}, but the file is not readable. It will be downloaded again");
                                    $md5 = '';
                                }
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
                                $startAt = $writer->isReadable($targetFile) ? $writer->getSize($targetFile) : null;
                            }

                            $progress->setMaxSteps(0);
                            $progress->setProgress(0);
                            $progress->setMessage("{$download->name} ({$download->platform}, {$download->language})");

                            $curlOptions = [];
                            if ($input->getOption('bandwidth') && defined('CURLOPT_MAX_RECV_SPEED_LARGE')) {
                                if (defined('CURLOPT_MAX_RECV_SPEED_LARGE')) {
                                    $curlOptions[CURLOPT_MAX_RECV_SPEED_LARGE] = $this->parseBandwidth($input->getOption('bandwidth'));
                                } else {
                                    $io->warning("Warning: You have specified a maximum bandwidth, but that's only available if your system has the PHP curl extension installed. Ignoring maximum bandwidth settings.");
                                }
                            }

                            $responses = $this->downloadManager->download(
                                download: $download,
                                callback: function (int $current, int $total) use ($startAt, $progress, $output) {
                                    if ($total > 0) {
                                        $progress->setMaxSteps($total + ($startAt ?? 0));
                                        $progress->setProgress($current + ($startAt ?? 0));
                                    }
                                },
                                startAt: $startAt,
                                httpTimeout: $timeout,
                                curlOptions: $curlOptions,
                            );

                            try {
                                $hash = $writer->getMd5HashContext($targetFile);
                            } catch (UnreadableFileException) {
                                $hash = hash_init('md5');
                            }
                            foreach ($responses as $response) {
                                $chunk = $response->getContent();
                                $writer->writeChunk($targetFile, $chunk, $chunkSize);
                                hash_update($hash, $chunk);
                            }
                            $hash = hash_final($hash);
                            $writer->finalizeWriting($targetFile, $hash);

                            if (!$noVerify && $download->md5 && $download->md5 !== $hash) {
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
        } catch (ExitException $e) {
            $io->error($e->getMessage());

            return $e->getCode();
        }
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

    private function handleSignals(OutputInterface $output): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGINT, function (int $signal, mixed $signalInfo) use ($output) {
            if ($this->canKillSafely || $this->exitRequested) {
                if (!$this->canKillSafely) {
                    throw new ExitException('Application termination has been requested twice, forcing killing');
                }
                throw new ExitException('Application has been terminated');
            }

            $this->exitRequested = true;
            $output->writeln('');
            $output->writeln('Application exit has been requested, the application will stop once the current download finishes. Press CTRL+C again to force exit.');
        });
    }

    private function dispatchSignals(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        if ($this->exitRequested) {
            throw new ExitException('Application has been terminated as requested previously.');
        }
    }

    private function parseBandwidth(string $bandwidth): int
    {
        if (is_numeric($bandwidth)) {
            return (int) $bandwidth;
        }

        $lower = strtolower($bandwidth);

        if (str_ends_with($lower, 'kb') || str_ends_with($lower, 'k')) {
            $value = (int) $lower;
            $value *= 1024;

            return $value;
        } else if (str_ends_with($lower, 'mb') || str_ends_with($lower, 'm')) {
            $value = (int) $lower;
            $value *= 1024 * 1024;

            return $value;
        } else if (str_ends_with($lower, 'b')) {
            return (int) $lower;
        }

        throw new InvalidArgumentException("Unsupported bandwidth format: {$bandwidth}");
    }
}
