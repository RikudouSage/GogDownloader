<?php

namespace App\Command;

use App\Enum\SizeUnit;
use App\Service\DownloadManager;
use App\Service\OwnedItemsManager;
use App\Trait\CommonOptionsTrait;
use App\Trait\FilteredGamesResolverTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('total-size', description: 'Calculates the total size of all your installers')]
final class TotalSizeCommand extends Command
{
    use CommonOptionsTrait;
    use FilteredGamesResolverTrait;

    public function __construct(
        private readonly DownloadManager $downloadManager,
        private readonly OwnedItemsManager $ownedItemsManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $unitValues = array_map(
            fn (SizeUnit $unit) => $unit->value,
            SizeUnit::cases(),
        );

        $this
            ->addOsFilterOption()
            ->addLanguageFilterOption()
            ->addLanguageFallbackEnglishOption()
            ->addUpdateOption()
            ->addExcludeLanguageOption()
            ->addHttpRetryOption()
            ->addHttpRetryDelayOption()
            ->addSkipHttpErrorsOption()
            ->addHttpIdleTimeoutOption()
            ->addGameNameFilterOption()
            ->addExcludeGameNameFilterOption()
            ->addOption(
                name: 'unit',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The unit to display the size in. Possible values: ' . implode(', ', $unitValues),
                default: SizeUnit::Gigabytes->value,
            )
            ->addOption(
                name: 'short',
                mode: InputOption::VALUE_NONE,
                description: 'When this flag is present, only the value is printed, no other text (including warnings) is outputted. Useful for scripting.',
            )
            ->addOption(
                'precision',
                mode: InputOption::VALUE_REQUIRED,
                description: 'How many decimal places should be used when outputting the number',
                default: 2,
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $games = $this->getGames($input, $output, $this->ownedItemsManager);

        $io->note('Note that if you have a lot of games, this command might take a long time.');

        $unit = SizeUnit::tryFrom($input->getOption('unit'));
        if ($unit === null) {
            $io->error("Unsupported unit, please use --help to see list of available units.");
            return Command::FAILURE;
        }

        $short = $input->getOption('short');
        $httpTimeout = $input->getOption('idle-timeout');
        $total = 0;
        foreach ($games as $game) {
            foreach ($game->downloads as $download) {
                if ($output->isVeryVerbose()) {
                    $io->comment("[{$game->title}] Downloading file '{$download->name}'");
                }
                $size = $this->downloadManager->getFileSize($download, $httpTimeout);
                if ($size === null && !$short) {
                    $io->warning("Failed getting size for {$download->name}, the results might be incomplete");
                    continue;
                }
                $total += $size;
            }
        }

        $total = $this->formatToUnit($total, $unit);
        $formatted = round($total, $input->getOption('precision'));

        if ($short) {
            $io->writeln($formatted);
        } else {
            $io->success("The total size of your downloads is: {$formatted} {$unit->name}");
        }

        return Command::SUCCESS;
    }

    private function formatToUnit(int $total, SizeUnit $unit): float
    {
        return match ($unit) {
            SizeUnit::Bytes => $total,
            SizeUnit::Kilobytes => $total / 1024,
            SizeUnit::Megabytes => $total / 1024 / 1024,
            SizeUnit::Gigabytes => $total / 1024 / 1024 / 1024,
            SizeUnit::Terabytes => $total / 1024 / 1024 / 1024 / 1024,
        };
    }
}
