<?php

namespace App\Command;

use App\Enum\NamingConvention;
use App\Enum\Setting;
use App\Service\OwnedItemsManager;
use App\Service\Persistence\PersistenceManager;
use App\Trait\MigrationCheckerTrait;
use App\Trait\TargetDirectoryTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('migrate-naming-scheme', description: 'Migrates the GOG naming scheme from the old format to the new one. Migrates both the database and your already downloaded files.')]
final class MigrateNamingSchemeCommand extends Command
{
    use TargetDirectoryTrait;
    use MigrationCheckerTrait;

    public function __construct(
        private readonly PersistenceManager $persistence,
        private readonly OwnedItemsManager $ownedItemsManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $defaultDirectory = $_ENV['DOWNLOAD_DIRECTORY']
            ?? $this->persistence->getSetting(Setting::DownloadPath)
            ?? null;

        $this->setHidden($this->persistence->getSetting(Setting::NamingConvention) !== NamingConvention::Custom->value);

        $this->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            'The target directory.',
            $defaultDirectory,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->showInfoIfMigrationsAreNeeded($io, $this->persistence);

        $configuredDirectory = $input->getArgument('directory');

        if (!$configuredDirectory) {
            if (!$input->isInteractive()) {
                $io->error('Refusing to do a potentially destructive operation without explicitly setting the target directory, please run this command in an interactive shell.');
                return Command::FAILURE;
            }

            if (!$io->askQuestion(new ConfirmationQuestion('You have not specified any target directory for your downloads, which means the current directory is used. Is that really what you want?', false))) {
                return Command::FAILURE;
            }
        }
        $input->setArgument('directory', $configuredDirectory ?? getcwd());

        $currentNamingConvention = NamingConvention::tryFrom($this->persistence->getSetting(Setting::NamingConvention)) ?? NamingConvention::GogSlug;
        if ($currentNamingConvention !== NamingConvention::Custom) {
            if (!$input->isInteractive()) {
                $io->error('Refusing to do a potentially destructive operation because the current naming convention is not the expected one, please run this command in an interactive shell.');
                return Command::FAILURE;
            }
            if (!$io->askQuestion(new ConfirmationQuestion("The current naming convention is not set to '" . NamingConvention::Custom->value . "', are you sure you want to run the migrate command?", false))) {
                return Command::FAILURE;
            }
        }

        $errors = [];
        $games = $this->ownedItemsManager->getLocalGameData();
        foreach ($games as $game) {
            if (!$game->slug) {
                $io->error("To migrate to the new naming scheme, please run the 'update' command first, currently there are no slug data for the game '{$game->title}' (and potentially others)");
                return Command::FAILURE;
            }

            $oldTargetDir = $this->getTargetDir($input, $game, namingScheme: NamingConvention::Custom);
            $newTargetDir = $this->getTargetDir($input, $game, namingScheme: NamingConvention::GogSlug);

            if (!is_dir($oldTargetDir)) {
                continue;
            }

            if (PHP_OS_FAMILY === 'Windows') {
                $suffix = '__MIGRATE__GD';
                if (!rename($oldTargetDir, $oldTargetDir . $suffix)) {
                    $io->error("Failed migrating game '{$game->title}', renaming '{$oldTargetDir}' to a temporary name '{$oldTargetDir}{$suffix}' failed.");
                    continue;
                }
                $oldTargetDir .= $suffix;
            }

            if (rename($oldTargetDir, $newTargetDir)) {
                $io->note("Migrated game '{$game->title}' from '{$oldTargetDir}' to '{$newTargetDir}'");
            } else {
                $io->error("Failed migrating game '{$game->title}', renaming '{$oldTargetDir}' to '{$newTargetDir}' failed.");
                $errors[] = $game->title;
            }
        }

        $this->persistence->storeSetting(Setting::NamingConvention, NamingConvention::GogSlug->value);

        if (count($errors) > 0) {
            $io->error('There were ' . count($errors) . ' errors migrating game data:');
            foreach ($errors as $error) {
                $io->error(' - ' . $error);
            }

            return Command::FAILURE;
        }

        $io->success('All game data were successfully migrated.');
        return Command::SUCCESS;
    }
}
