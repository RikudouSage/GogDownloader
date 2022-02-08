<?php

namespace App\Command;

use App\DTO\DownloadDescription;
use App\DTO\GameDetail;
use App\Enum\Language;
use App\Enum\OperatingSystem;
use App\Service\DownloadManager;
use App\Service\OwnedItemsManager;
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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $noVerify = $input->getOption('no-verify');
        $operatingSystem = OperatingSystem::tryFrom($input->getOption('os'));
        $language = Language::tryFrom($input->getOption('language'));
        $englishFallback = $input->getOption('language-fallback-english');

        if ($language !== null && $language !== Language::English && !$englishFallback) {
            $io->warning("GOG often has multiple language versions inside the English one. Those game files will be skipped. Specify --language-fallback-english to include English versions if your language's version doesn't exist.");
        }

        foreach ($this->ownedItemsManager->getLocalGameData() as $game) {
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

            foreach ($downloads as $download) {
                $progress = $io->createProgressBar();
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

                $targetFile = "{$this->getTargetDir($input, $game)}/{$this->downloadManager->getFilename($download)}";
                $startAt = null;
                if (($download->md5 || $noVerify) && file_exists($targetFile)) {
                    $md5 = $noVerify ? '' : md5_file($targetFile);
                    if (!$noVerify && $download->md5 === $md5) {
                        if ($output->isVerbose()) {
                            $io->writeln(
                                "{$download->name} ({$download->platform}, {$download->language}): Skipping because it exists and is valid",
                            );
                        }
                        continue;
                    } elseif ($noVerify) {
                        if ($output->isVerbose()) {
                            $io->writeln("{$download->name} ({$download->platform}, {$download->language}): Skipping because it exists (--no-verify specified, not checking content)");
                        }
                        continue;
                    }
                    $startAt = filesize($targetFile);
                }

                if ($operatingSystem !== null && $download->platform !== $operatingSystem->value) {
                    if ($output->isVerbose()) {
                        $io->writeln("{$download->name} ({$download->platform}, {$download->language}): Skipping because of OS filter");
                    }
                    continue;
                }

                if (
                    $language !== null
                    && $download->language !== $language->getLocalName()
                    && (!$englishFallback || $download->language !== Language::English->getLocalName())
                ) {
                    if ($output->isVerbose()) {
                        $io->writeln("{$download->name} ({$download->platform}, {$download->language}): Skipping because of language filter");
                    }
                    continue;
                }

                $progress->setMaxSteps(0);
                $progress->setProgress(0);
                $progress->setMessage("{$download->name} ({$download->platform}, {$download->language})");

                $responses = $this->downloadManager->download($download, function (int $current, int $total) use ($progress, $output) {
                    if ($total > 0) {
                        $progress->setMaxSteps($total);
                        $progress->setProgress($current);
                    }
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
                    $io->warning("{$download->name} ({$download->platform}, {$download->language}) failed hash check");
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
