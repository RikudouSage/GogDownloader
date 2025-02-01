<?php

namespace App\Command;

use App\DTO\GameDetail;
use App\DTO\OwnedItemInfo;
use App\DTO\SearchFilter;
use App\Enum\Language;
use App\Enum\MediaType;
use App\Enum\OperatingSystem;
use App\Exception\TooManyRetriesException;
use App\Service\OwnedItemsManager;
use App\Service\RetryService;
use App\Trait\EnumExceptionParserTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use ValueError;

#[AsCommand('update-database')]
final class UpdateDatabaseCommand extends Command
{
    use EnumExceptionParserTrait;

    public function __construct(
        private readonly OwnedItemsManager $ownedItemsManager,
        private readonly RetryService $retryService,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Updates the games/files database.')
            ->addOption(
                'new-only',
                null,
                InputOption::VALUE_NONE,
                'Download information only about new games',
            )
            ->addOption(
                'updated-only',
                'u',
                InputOption::VALUE_NONE,
                'Download information only about updated games',
            )
            ->addOption(
                'clear',
                'c',
                InputOption::VALUE_NONE,
                'Clear local database before updating it',
            )
            ->addOption(
                'search',
                's',
                InputOption::VALUE_REQUIRED,
                'Update only games that match the given search',
            )
            ->addOption(
                'os',
                'o',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Filter by OS, allowed values are: ' . implode(
                    ', ',
                    array_map(
                        fn (OperatingSystem $os) => $os->value,
                        OperatingSystem::cases(),
                    )
                ),
            )
            ->addOption(
                'language',
                'l',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Filter by language, for list of languages run "languages"'
            )
            ->addOption(
                'include-hidden',
                null,
                InputOption::VALUE_NONE,
                'Include hidden games in the update',
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
            ->setAliases(['update'])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clear')) {
            $this->ownedItemsManager->storeGamesData([]);
        }

        $storedItems = $this->ownedItemsManager->getLocalGameData();
        $storedItemIds = array_map(fn (GameDetail $detail) => $detail->id, $storedItems);
        $timeout = $input->getOption('idle-timeout');

        try {
            $languages = array_map(
                fn (string $langCode) => Language::from($langCode),
                $input->getOption('language'),
            );
        } catch (ValueError $e) {
            $invalid = $this->getInvalidOption($e);
            $io->error(
                $invalid
                    ? 'Some of the languages you provided are not valid'
                    : "The language '{$invalid}' is not a supported language'"
            );

            return Command::FAILURE;
        }

        try {
            $operatingSystems = array_map(
                fn (string $operatingSystem) => OperatingSystem::from($operatingSystem),
                $input->getOption('os'),
            );
        } catch (ValueError $e) {
            $invalid = $this->getInvalidOption($e);
            $io->error(
                $invalid
                    ? 'Some of the operating systems you provided are not valid'
                    : "The operating system '{$invalid}' is not a valid operating system"
            );

            return Command::FAILURE;
        }

        $filter = new SearchFilter(
            operatingSystems: $operatingSystems,
            languages: $languages,
            search: $input->getOption('search'),
            includeHidden: $input->getOption('include-hidden'),
        );

        foreach ($this->getTypes($input) as $type) {
            $items = $this->ownedItemsManager->getOwnedItems(
                mediaType: $type,
                filter: $filter,
                productsCount: $count,
                httpTimeout: $timeout,
            );
            if ($input->getOption('new-only')) {
                $items = array_filter([...$items], fn (OwnedItemInfo $item) => !in_array($item->getId(), $storedItemIds));
                $count = count($items);
            }
            if ($input->getOption('updated-only')) {
                $items = array_filter([...$items], function (OwnedItemInfo $item) use ($storedItemIds) {
                    return $item->hasUpdates() || !in_array($item->getId(), $storedItemIds);
                });
                $count = count($items);
            }
            $progressBar = null;
            foreach ($items as $item) {
                try {
                    $this->retryService->retry(function () use ($item, $count, $io, &$progressBar) {
                        if ($progressBar === null) {
                            $progressBar = $io->createProgressBar($count);
                            $progressBar->setFormat(
                                ' %current%/%max% [%bar%] %percent:3s%% - %message%'
                            );
                            $progressBar->setMessage($item->getTitle());
                            $progressBar->advance();
                        }
                        $progressBar->setMessage($item->getTitle());
                        $this->ownedItemsManager->storeSingleGameData(
                            $this->ownedItemsManager->getItemDetail($item, cached: false),
                        );
                        $progressBar->advance();
                    }, $input->getOption('retry'), $input->getOption('retry-delay'));
                } catch (TooManyRetriesException $e) {
                    if (!$input->getOption('skip-errors')) {
                        throw $e;
                    }
                    $io->note("{$item->getTitle()} couldn't be downloaded");
                }
            }
            $progressBar?->finish();
        }

        $io->success('Local data successfully updated');

        return self::SUCCESS;
    }

    /**
     * @return array<MediaType>
     */
    private function getTypes(InputInterface $input): array
    {
        //$games = $input->getOption('games');
        //$movies = $input->getOption('movies');
        //
        $result = [];
        //
        //if ($games) {
        //    $result[] = MediaType::Game;
        //}
        //if ($movies) {
        //    $result[] = MediaType::Movie;
        //}
        //
        if (!count($result)) {
            $result = [MediaType::Game/*MediaType::Movie*/];
        }

        return $result;
    }
}
