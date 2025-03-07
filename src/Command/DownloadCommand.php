<?php

namespace App\Command;

use App\DTO\GameInstaller;
use App\DTO\GameExtra;
use App\Enum\Language;
use App\Enum\NamingConvention;
use App\Enum\Setting;
use App\Exception\ExitException;
use App\Exception\ForceRetryException;
use App\Exception\InvalidValueException;
use App\Exception\RetryDownloadForUnmatchingHashException;
use App\Exception\TooManyRetriesException;
use App\Exception\UnreadableFileException;
use App\Helper\LatelyBoundStringValue;
use App\Service\DownloadManager;
use App\Service\FileWriter\FileWriterLocator;
use App\Service\Iterables;
use App\Service\OwnedItemsManager;
use App\Service\Persistence\PersistenceManager;
use App\Service\RetryService;
use App\Trait\CommonOptionsTrait;
use App\Trait\EnumExceptionParserTrait;
use App\Trait\FilteredGamesResolverTrait;
use App\Trait\MigrationCheckerTrait;
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
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[AsCommand('download')]
final class DownloadCommand extends Command
{
    use TargetDirectoryTrait;
    use EnumExceptionParserTrait;
    use CommonOptionsTrait;
    use FilteredGamesResolverTrait;
    use MigrationCheckerTrait;

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
        $this
            ->setDescription('Downloads all files from the local database (see update command). Can resume downloads unless --no-verify is specified.')
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
            ->addOption(
                name: 'extras',
                shortcut: 'e',
                mode: InputOption::VALUE_NONE,
                description: 'Whether to include extras or not.',
            )
            ->addOption(
                name: 'skip-existing-extras',
                mode: InputOption::VALUE_NONE,
                description: "Unlike games, extras generally don't have a hash that can be used to check whether the downloaded content is the same as the remote one, meaning by default extras will be downloaded every time, even if they exist. By providing this flag, you will skip existing extras."
            )
            ->addOption(
                name: 'no-games',
                mode: InputOption::VALUE_NONE,
                description: 'Skip downloading games. Should be used with other options like --extras if you want to only download those.'
            )
            ->addOption(
                name: 'skip-download',
                mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                description: 'Skip a download by its name, can be specified multiple times.'
            )
            ->addOption(
                name: 'remove-invalid',
                mode: InputOption::VALUE_NONE,
                description: 'Remove downloaded files that failed hash check and try downloading it again.'
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
            $skipExistingExtras = $input->getOption('skip-existing-extras');
            $iterable = $this->getGames($input, $output, $this->ownedItemsManager);
            $downloadsToSkip = $input->getOption('skip-download');
            $removeInvalid = $input->getOption('remove-invalid');

            $this->dispatchSignals();
            foreach ($iterable as $game) {
                $downloads = [];
                if (!$input->getOption('no-games')) {
                    $downloads = [...$downloads, ...$game->downloads];
                }
                if ($input->getOption('extras')) {
                    $downloads = [...$downloads, ...$game->extras];
                }

                foreach ($downloads as $download) {
                    try {
                        $this->retryService->retry(function (?Throwable $retryReason) use (
                            $removeInvalid,
                            $downloadsToSkip,
                            $skipExistingExtras,
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
                            assert($download instanceof GameInstaller || $download instanceof GameExtra);

                            $downloadTag = new LatelyBoundStringValue(function () use ($download, $game) {
                                if ($download instanceof GameInstaller) {
                                    return "[{$game->title}] {$download->name} ({$download->platform}, {$download->language})";
                                } else if ($download instanceof GameExtra) {
                                    return "[{$game->title}] {$download->name} (extra)";
                                }

                                throw new RuntimeException('Uncovered download type');
                            });

                            if (in_array($download->name, $downloadsToSkip, true)) {
                                if ($output->isVerbose()) {
                                    $io->writeln("{$downloadTag}: Skipping because it's specified using the --skip-download flag");
                                }

                                return;
                            }

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
                                throw new RuntimeException("{$downloadTag}: Failed getting the filename for {$download->name}");
                            }

                            if ($download instanceof GameExtra) {
                                $filename = "extras/{$filename}";
                            }

                            $targetFile = $writer->getFileReference("{$targetDir}/{$filename}");

                            $startAt = null;
                            if (
                                (
                                    $download->md5
                                    || $noVerify
                                    || ($download instanceof GameExtra && $skipExistingExtras)
                                )
                                && $writer->exists($targetFile)
                            ) {
                                try {
                                    $md5 = $noVerify ? '' : $writer->getMd5Hash($targetFile);
                                } catch (UnreadableFileException) {
                                    $io->warning("{$downloadTag}: Tried to get existing hash of {$download->name}, but the file is not readable. It will be downloaded again");
                                    $md5 = '';
                                }
                                if (!$noVerify && $download->md5 === $md5) {
                                    if ($output->isVerbose()) {
                                        $io->writeln(
                                            "{$downloadTag}: Skipping because it exists and is valid",
                                        );
                                    }

                                    return;
                                } elseif ($download instanceof GameExtra && $skipExistingExtras) {
                                    if ($output->isVerbose()) {
                                        $io->writeln("{$downloadTag}: Skipping because it exists (--skip-existing-extras specified, not checking content)");
                                    }

                                    return;
                                } elseif ($noVerify) {
                                    if ($output->isVerbose()) {
                                        $io->writeln("{$downloadTag}: Skipping because it exists (--no-verify specified, not checking content)");
                                    }

                                    return;
                                }
                                $startAt = $writer->isReadable($targetFile) ? $writer->getSize($targetFile) : null;
                            }

                            $progress->setMaxSteps(0);
                            $progress->setProgress(0);
                            $progress->setMessage($downloadTag);

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
                            try {
                                foreach ($responses as $response) {
                                    $chunk = $response->getContent();
                                    $writer->writeChunk($targetFile, $chunk, $chunkSize);
                                    hash_update($hash, $chunk);
                                }
                            } catch (ClientException $e) {
                                if ($e->getCode() === Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE) {
                                    $writer->remove($targetFile);
                                    throw new ForceRetryException();
                                }

                                throw $e;
                            }
                            $hash = hash_final($hash);
                            $writer->finalizeWriting($targetFile, $hash);

                            if (!$noVerify && $download->md5 && $download->md5 !== $hash) {
                                if ($removeInvalid && !$retryReason instanceof RetryDownloadForUnmatchingHashException) {
                                    $io->warning("{$downloadTag} failed hash check. The file will be removed and the process retried.");
                                    $writer->remove($targetFile);
                                    throw new RetryDownloadForUnmatchingHashException();
                                } else if ($removeInvalid) {
                                    $io->warning("{$downloadTag} failed hash check twice, not downloading again.");
                                } else {
                                    $io->warning("{$downloadTag} failed hash check. The file will be kept as is, specify --remove-invalid if you want to delete such files and retry the download.");
                                }
                            }

                            $progress->finish();
                            $io->newLine();
                        }, maxRetries: $input->getOption('retry'), retryDelay: $input->getOption('retry-delay'), ignoreExceptions: [
                            InvalidValueException::class,
                            ExitException::class,
                        ]);
                    } catch (TooManyRetriesException $e) {
                        if (!$input->getOption('skip-errors')) {
                            if (!count($e->exceptions)) {
                                throw $e;
                            }
                            throw $e->exceptions[array_key_last($e->exceptions)];
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
