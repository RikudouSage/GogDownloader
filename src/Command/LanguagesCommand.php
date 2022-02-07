<?php

namespace App\Command;

use App\Enum\Language;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('languages')]
final class LanguagesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Lists all supported languages')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        foreach (Language::cases() as $case) {
            $io->writeln("- {$case->value} ({$case->name})");
        }

        return self::SUCCESS;
    }
}
