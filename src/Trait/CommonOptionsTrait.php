<?php

namespace App\Trait;

use App\Enum\OperatingSystem;
use Symfony\Component\Console\Input\InputOption;

trait CommonOptionsTrait
{
    private function addOsFilterOption(): static
    {
        $this->addOption(
            'os',
            'o',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Only games for specified operating system, allowed values: ' . implode(
                ', ',
                array_map(
                    fn(OperatingSystem $os) => $os->value,
                    OperatingSystem::cases(),
                )
            )
        );

        return $this;
    }

    private function addLanguageFilterOption(): static
    {
        $this
            ->addOption(
                'language',
                'l',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Only games for specified language. See command "languages" for list of them.',
            );

        return $this;
    }

    private function addLanguageFallbackEnglishOption(): static
    {
        $this
            ->addOption(
                'language-fallback-english',
                null,
                InputOption::VALUE_NONE,
                'Use english versions of games when the specified language is not found.',
            );

        return $this;
    }

    private function addUpdateOption(): static
    {
        $this
            ->addOption(
                'update',
                'u',
                InputOption::VALUE_NONE,
                "If you specify this flag, the local database will be updated along with this command and you don't need to update it separately"
            );

        return $this;
    }

    private function addExcludeLanguageOption(): static
    {
        $this
            ->addOption(
                'exclude-game-with-language',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify a language to exclude. If a game supports this language, it will be skipped.',
            );

        return $this;
    }

    private function addHttpRetryOption(): static
    {
        $this
            ->addOption(
                'retry',
                null,
                InputOption::VALUE_REQUIRED,
                'How many times should each request be retried in case of failure.',
                3,
            );

        return $this;
    }

    private function addHttpRetryDelayOption(): static
    {
        $this
            ->addOption(
                'retry-delay',
                null,
                InputOption::VALUE_REQUIRED,
                'The delay in seconds between each retry.',
                1,
            );

        return $this;
    }

    private function addSkipHttpErrorsOption(): static
    {
        $this
            ->addOption(
                'skip-errors',
                null,
                InputOption::VALUE_NONE,
                "Skip games that for whatever reason couldn't be fetched"
            );

        return $this;
    }

    private function addHttpIdleTimeoutOption(): static
    {
        $this
            ->addOption(
                'idle-timeout',
                null,
                InputOption::VALUE_REQUIRED,
                'Set the idle timeout in seconds for http requests',
                3,
            );

        return $this;
    }

    private function addGameNameFilterOption(): static
    {
        $this
            ->addOption(
                name: 'only',
                mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                description: 'Only games specified using this flag will be fetched. The flag can be specified multiple times. Case insensitive, exact match.',
            );

        return $this;
    }

    private function addExcludeGameNameFilterOption(): static
    {
        $this
            ->addOption(
                name: 'without',
                mode: InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                description: "Don't download the games listed using this flag. The flag can be specified multiple times. Case insensitive, exact match.",
            );

        return $this;
    }
}
