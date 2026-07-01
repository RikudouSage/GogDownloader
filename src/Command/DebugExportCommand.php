<?php

namespace App\Command;

use App\Service\Persistence\PersistenceManager;
use App\Service\Persistence\PersistenceManagerFiles;
use App\Service\Persistence\PersistenceManagerSqlite;
use Closure;
use PDO;
use PDOException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'debug-export', description: 'Exports the database (without authentication credentials) to a file for debugging purposes.')]
final class DebugExportCommand extends Command
{
    public function __construct(
        private readonly PersistenceManager $persistence,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->persistence instanceof PersistenceManagerFiles) {
            return $this->handleFileExport($io, $input, $output);
        } else if ($this->persistence instanceof PersistenceManagerSqlite) {
            return $this->handleSqliteExport($io);
        }

        $io->error(sprintf('Unsupported persistence manager: %s', $this->persistence::class));
        return Command::FAILURE;
    }

    private function handleFileExport(SymfonyStyle $io, InputInterface $input, OutputInterface $output): int
    {
        assert($this->persistence instanceof PersistenceManagerFiles);
        $getFullPath = Closure::bind(fn() => $this->getFullPath(...), $this->persistence, PersistenceManagerFiles::class)();

        $gameFile = Closure::bind(fn () => $this::GAME_FILE, $this->persistence, PersistenceManagerFiles::class)();
        $settingsFile = Closure::bind(fn () => $this::SETTINGS_FILE, $this->persistence, PersistenceManagerFiles::class)();
        $hashesFile = Closure::bind(fn () => $this::HASHES_FILE, $this->persistence, PersistenceManagerFiles::class)();

        $content = "===== {$gameFile} =====";

        $fullPath = $getFullPath($gameFile);
        if (file_exists($fullPath)) {
            $content .= PHP_EOL . file_get_contents($fullPath);
        } else {
            $io->warning("{$gameFile} does not exist, skipping.");
        }

        $content .= PHP_EOL . "===== {$settingsFile} =====";
        $fullPath = $getFullPath($settingsFile);
        if (file_exists($fullPath)) {
            $content .= PHP_EOL . file_get_contents($fullPath);
        } else {
            $io->warning("{$settingsFile} does not exist, skipping.");
        }

        $content .= PHP_EOL . "===== {$hashesFile} =====";
        $fullPath = $getFullPath($hashesFile);
        if (file_exists($fullPath)) {
            $content .= PHP_EOL . file_get_contents($fullPath);
        } else {
            $io->warning("{$hashesFile} does not exist, skipping.");
        }

        $outPath = getcwd() . '/debug-export.txt';
        if (!file_put_contents($outPath, $content)) {
            $io->error(sprintf('Failed to write debug export to "%s"', $outPath));
            return Command::FAILURE;
        }

        $this->printSuccessMessage($io, $outPath);
        return Command::SUCCESS;
    }

    private function handleSqliteExport(SymfonyStyle $io): int
    {
        assert($this->persistence instanceof PersistenceManagerSqlite);
        $getFullPath = Closure::bind(fn() => $this->getFullPath(...), $this->persistence, PersistenceManagerSqlite::class)();
        $database = Closure::bind(fn () => $this::DATABASE, $this->persistence, PersistenceManagerSqlite::class)();

        $dbPath = $getFullPath($database);
        if (!file_exists($dbPath)) {
            $io->error(sprintf('Database file "%s" does not exist, nothing to export. Are you running the command in the same directory/folder you download games from?', $dbPath));
            return Command::FAILURE;
        }

        $outPath = getcwd() . '/debug-export.sqlite';
        if (!copy($dbPath, $outPath)) {
            $io->error(sprintf('Failed to copy database file "%s" to "%s"', $dbPath, $outPath));
            return Command::FAILURE;
        }

        try {
            $pdo = new PDO("sqlite:{$outPath}");
        } catch (PDOException) {
            $io->error(sprintf('Failed to open database file "%s" for reading', $outPath));
            if (!@unlink($outPath)) {
                $io->error(sprintf('Failed to delete database file "%s", DO NOT SHARE THE FILE WITH ANYONE, IT CONTAINS YOUR CREDENTIALS.', $outPath));
            }
            return Command::FAILURE;
        }

        if ($pdo->exec('PRAGMA secure_delete = ON') === false) {
            $io->error('Failed to enable secure deletion of authorization data.');
            if (!@unlink($outPath)) {
                $io->error(sprintf('Failed to delete database file "%s", DO NOT SHARE THE FILE WITH ANYONE, IT CONTAINS YOUR CREDENTIALS.', $outPath));
            }

            return Command::FAILURE;
        }
        if ($pdo->exec('delete from auth') === false) {
            $io->error('Failed to delete authorization data.');
            if (!@unlink($outPath)) {
                $io->error(sprintf('Failed to delete database file "%s", DO NOT SHARE THE FILE WITH ANYONE, IT CONTAINS YOUR CREDENTIALS.', $outPath));
            }

            return Command::FAILURE;
        }
        if ($pdo->exec('VACUUM') === false) {
            $io->error('Failed to compact database after deleting authorization data.');
            if (!@unlink($outPath)) {
                $io->error(sprintf('Failed to delete database file "%s", DO NOT SHARE THE FILE WITH ANYONE, IT CONTAINS YOUR CREDENTIALS.', $outPath));
            }

            return Command::FAILURE;
        }

        unset($pdo);

        $this->printSuccessMessage($io, $outPath);
        return Command::SUCCESS;
    }

    private function printSuccessMessage(SymfonyStyle $io, string $outPath): void
    {
        $io->success([
            sprintf('Database successfully exported to %s.', $outPath),
            "This file contains all your data except for credentials which means it's safe to share.",
            'That does NOT mean you should share it publicly because it contains a lot of other data about your games and could be used to track down your behaviour.',
            'In conclusion: This is safe to share with the developer for debugging purposes but public sharing should be avoided.',
        ]);
    }
}
