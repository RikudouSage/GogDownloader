<?php

namespace App\Command;

use App\DTO\GameDetail;
use App\DTO\OwnedItemInfo;
use App\Enum\Language;
use App\Enum\MediaType;
use App\Enum\OperatingSystem;
use App\Service\OwnedItemsManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('update-database')]
final class UpdateDatabaseCommand extends Command
{
    public function __construct(
        private readonly OwnedItemsManager $ownedItemsManager,
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
                null,
                InputOption::VALUE_NONE,
                'Download information only about updated games',
            )
            ->addOption(
                'os',
                'o',
                InputOption::VALUE_REQUIRED,
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
                InputOption::VALUE_REQUIRED,
                'Filter by language, for list of languages run "languages"'
            )
            ->setAliases(['update'])
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $storedItems = $this->ownedItemsManager->getLocalGameData();
        $storedItemIds = array_map(fn (GameDetail $detail) => $detail->id, $storedItems);

        foreach ($this->getTypes($input) as $type) {
            $items = $this->ownedItemsManager->getOwnedItems(
                $type,
                language: Language::tryFrom($input->getOption('language')),
                operatingSystem: OperatingSystem::tryFrom($input->getOption('os')),
                productsCount: $count
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
                if ($progressBar === null) {
                    $progressBar = $io->createProgressBar($count);
                    $progressBar->setFormat(
                        ' %current%/%max% [%bar%] %percent:3s%% - %message%'
                    );
                }
                $progressBar->setMessage($item->getTitle());
                $progressBar->advance();
                $this->ownedItemsManager->storeSingleGameData(
                    $this->ownedItemsManager->getItemDetail($item),
                );
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
