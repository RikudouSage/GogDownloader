<?php

namespace App\Trait;

use App\DTO\DownloadDescription;
use App\DTO\GameDetail;
use App\DTO\OwnedItemInfo;
use App\DTO\SearchFilter;
use App\Enum\Language;
use App\Enum\MediaType;
use App\Enum\OperatingSystem;
use App\Exception\InvalidValueException;
use App\Service\OwnedItemsManager;
use Rikudou\Iterables\Iterables;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use ValueError;

trait FilteredGamesResolverTrait
{
    use EnumExceptionParserTrait;

    /**
     * @return array<Language>
     * @throws InvalidValueException
     */
    private function getLanguages(InputInterface $input): array
    {
        try {
            return array_map(
                fn (string $langCode) => Language::from($langCode),
                $input->getOption('language'),
            );
        } catch (ValueError $e) {
            $invalid = $this->getInvalidOption($e);
            throw new InvalidValueException(
                $invalid
                    ? 'Some of the languages you provided are not valid'
                    : "The language '{$invalid}' is not a supported language'"
            );
        }
    }

    /**
     * @return array<OperatingSystem>
     * @throws InvalidValueException
     */
    private function getOperatingSystems(InputInterface $input): array
    {
        try {
            return array_map(
                fn(string $operatingSystem) => OperatingSystem::from($operatingSystem),
                $input->getOption('os'),
            );
        } catch (ValueError $e) {
            $invalid = $this->getInvalidOption($e);
            throw new InvalidValueException(
                $invalid
                    ? 'Some of the operating systems you provided are not valid'
                    : "The operating system '{$invalid}' is not a valid operating system");
        }
    }

    /**
     * @return iterable<GameDetail>
     * @throws InvalidValueException
     */
    private function getGames(InputInterface $input, OutputInterface $output, OwnedItemsManager $ownedItemsManager): iterable
    {
        $operatingSystems = $this->getOperatingSystems($input);
        $languages = $this->getLanguages($input);

        $englishFallback = $input->getOption('language-fallback-english');
        $excludeLanguage = Language::tryFrom($input->getOption('exclude-game-with-language') ?? '');
        $timeout = $input->getOption('idle-timeout');
        $only = $input->getOption('only');
        $without = $input->getOption('without');

        $filter = new SearchFilter(
            operatingSystems: $operatingSystems,
            languages: $languages,
        );

        $iterable = $input->getOption('update')
            ? Iterables::map(
                function (OwnedItemInfo $info) use ($ownedItemsManager, $timeout, $output): GameDetail {
                    if ($output->isVerbose()) {
                        $output->writeln("Updating metadata for {$info->getTitle()}...");
                    }

                    return $ownedItemsManager->getItemDetail($info, $timeout);
                },
                $ownedItemsManager->getOwnedItems(MediaType::Game, $filter, httpTimeout: $timeout),
            )
            : $ownedItemsManager->getLocalGameData();

        if ($only) {
            $iterable = Iterables::filter($iterable, fn (GameDetail $detail) => in_array(
                strtolower($detail->title),
                array_map(fn (string $title) => strtolower($title), $only),
                true,
            ));
        }
        if ($without) {
            $iterable = Iterables::filter($iterable, fn (GameDetail $detail) => !in_array(
                strtolower($detail->title),
                array_map(fn (string $title) => strtolower($title), $without),
                true,
            ));
        }

        if ($excludeLanguage) {
            $iterable = Iterables::filter(
                $iterable,
                fn (GameDetail $detail) => array_find(
                    $detail->downloads,
                    fn (DownloadDescription $download) => $download->language === $excludeLanguage->getLocalName()
                ) === null,
            );
        }

        if ($englishFallback && $languages) {
            $iterable = Iterables::map(
                function (GameDetail $game) use ($languages) {
                    $downloads = array_filter(
                        $game->downloads,
                        fn (DownloadDescription $download) => in_array($download->language, array_map(
                            fn (Language $language) => $language->getLocalName(),
                            $languages,
                        ), true),
                    );
                    if (!count($downloads)) {
                        $downloads = array_filter(
                            $game->downloads,
                            fn (DownloadDescription $download) => $download->language === Language::English->getLocalName(),
                        );
                    }

                    return new GameDetail(
                        id: $game->id,
                        title: $game->title,
                        cdKey: $game->cdKey,
                        downloads: $downloads,
                    );
                },
                $iterable,
            );
        }

        return $iterable;
    }
}
