<?php

namespace App\Command;

use App\DTO\GameDetail;
use App\Exception\ExitException;
use App\Service\DownloadManager;
use App\Service\OwnedItemsManager;
use App\Service\Persistence\PersistenceManager;
use App\Trait\MigrationCheckerTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('games', description: 'Gets details about a game you own, or lists your games.')]
final class GamesCommand extends Command
{
    use MigrationCheckerTrait;

    public function __construct(
        private readonly OwnedItemsManager $ownedItemsManager,
        private readonly DownloadManager $downloadManager,
        private readonly PersistenceManager $persistence,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'name',
                mode: InputArgument::OPTIONAL,
                description: 'The name of the game to get details for',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->showInfoIfMigrationsAreNeeded($io, $this->persistence);

        try {
            $gameName = $input->getArgument('name') ?? $this->selectGame($input, $io);
            if (!$input->getArgument('name') && $input->isInteractive()) {
                $io->note("Tip: Next time you can add \"{$gameName}\" directly as an argument to this command.");
            }

            if (!$input->isInteractive() && !$gameName) {
                $io->writeln(array_map(
                    fn (GameDetail $detail) => $detail->title,
                    $this->ownedItemsManager->getLocalGameData(),
                ));

                return Command::SUCCESS;
            }

            $detail = $this->ownedItemsManager->getGameDetailByTitle($gameName);
            if ($detail === null) {
                $io->error("Could not find a game with title '{$gameName}' in your local database. Perhaps try running the update command first?");

                return Command::FAILURE;
            }

            $io->table([
                'ID', 'Title',
            ], [
                [$detail->id, $detail->title],
            ]);

            $headers = ['Language', 'Platform', 'Name', 'Size', 'MD5', 'URL'];
            $rows = [];

            foreach ($detail->downloads as $download) {
                $rows[] = [
                    $download->language,
                    $download->platform,
                    $download->name,
                    ($download->size / 1024 / 1024) . ' MB',
                    $download->md5,
                    $this->downloadManager->getDownloadUrl($download),
                ];
            }

            if (!count($rows)) {
                $io->warning("The game does not contain any files to download. Perhaps try running the update command first?");
            }

            $io->table($headers, $rows);

            return Command::SUCCESS;
        } catch (ExitException $e) {
            if ($e->getMessage()) {
                $io->error($e->getMessage());
            }

            return $e->getCode();
        }
    }

    private function selectGame(InputInterface $input, SymfonyStyle $io): ?string
    {
        $items = $this->ownedItemsManager->getLocalGameData();
        if (!count($items)) {
            $io->note("You don't have any games available. Perhaps try running the update command first?");
            throw new ExitException(code: Command::SUCCESS);
        }

        $question = (new ChoiceQuestion(
            'Please select the game you wish to get details for:',
            array_map(fn (GameDetail $detail) => $detail->title, $items),
        ));

        $helper = $this->getHelper('question');

        return $helper->ask($input, $io, $question);
    }
}
