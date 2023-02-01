<?php

namespace App\Command;

use App\Enum\Setting;
use App\Service\Persistence\PersistenceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('config', description: 'Configure various settings, like default location and so on')]
final class ConfigCommand extends Command
{
    public function __construct(
        private readonly PersistenceManager $persistence,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $possibleSettings = array_map(fn (Setting $setting) => $setting->value, Setting::cases());
        $this
            ->addArgument(
                'setting',
                InputArgument::OPTIONAL,
                'The name of the setting, possible values: ' . implode(', ', $possibleSettings),
                null,
                array_map(fn (Setting $setting) => $setting->value, Setting::cases()),
            )
            ->addArgument(
                'value',
                InputArgument::OPTIONAL,
                "The new value. If this argument is present a new value gets saved, if it's omitted the current value gets printed. Use a special value 'null' to reset the setting to default."
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $settingName = $input->getArgument('setting');

        if ($settingName === null) {
            $rows = [];
            foreach (Setting::cases() as $setting) {
                $rows[] = [
                    $setting->value,
                    $this->persistence->getSetting($setting) ?? '<error>-- no value --</error>',
                ];
            }

            $io->table(['Setting', 'Value'], $rows);

            return self::SUCCESS;
        }

        $setting = Setting::tryFrom($settingName);
        if ($setting === null) {
            $io->error("Invalid setting: '{$settingName}'");

            return self::FAILURE;
        }

        $value = $input->getArgument('value');

        if ($value !== null) {
            if ($value === 'null') {
                $value = null;
            }
            $this->persistence->storeSetting($setting, $value);
            $io->success("Setting '{$setting->value}' successfully set.");

            return self::SUCCESS;
        }

        $io->writeln($this->persistence->getSetting($setting) ?? '<error> -- no value -- </error>');

        return self::SUCCESS;
    }
}
