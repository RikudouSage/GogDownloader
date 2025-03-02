<?php

namespace App\Trait;

use App\DTO\DownloadableItem;
use App\DTO\GameDetail;
use App\DTO\PlatformSpecificItem;
use App\Enum\NamingConvention;
use App\Enum\OperatingSystem;
use App\Enum\Setting;
use App\Exception\InvalidValueException;
use LogicException;
use Symfony\Component\Console\Input\InputInterface;

trait TargetDirectoryTrait
{
    private function getTargetDir(InputInterface $input, GameDetail $game, ?DownloadableItem $download = null, ?string $subdirectory = null, ?NamingConvention $namingScheme = null): string
    {
        $dir = $input->getArgument('directory')
            ?? $_ENV['DOWNLOAD_DIRECTORY']
            ?? $this->persistence->getSetting(Setting::DownloadPath)
            ?? getcwd()
        ;

        if (PHP_OS_FAMILY === 'Windows') {
            if (!preg_match('/^[a-zA-Z]:\\\\/', $dir)) {
                $dir = getcwd() . '/' . $dir;
            }
        } else {
            if (!str_starts_with($dir, '/') && !preg_match('@^[0-9a-zA-Z.]+://.+$@', $dir)) {
                $dir = getcwd() . '/' . $dir;
            }
        }

        $namingScheme ??= NamingConvention::tryFrom(
            $this->persistence->getSetting(Setting::NamingConvention) ?? '',
        ) ?? NamingConvention::GogSlug;

        switch ($namingScheme) {
            case NamingConvention::GogSlug:
            case NamingConvention::RomManager:
                if (!$game->slug) {
                    throw new InvalidValueException("GOG Downloader is configured to use the GOG slug naming scheme, but the game '{$game->title}' does not have a slug. If you migrated from the previous naming scheme, please run the update command first.");
                }
                $title = $game->slug;
                break;
            case NamingConvention::Custom:
                $title = preg_replace('@[^a-zA-Z-_0-9.]@', '_', $game->title);
                $title = preg_replace('@_{2,}@', '_', $title);
                $title = trim($title, '.');
                break;
            default:
                throw new LogicException('Unimplemented naming scheme: ' . $namingScheme->value);
        }

        if ($namingScheme === NamingConvention::RomManager) {
            if (!$download instanceof PlatformSpecificItem) {
                $dir .= "/win";
            } else {
                $os = OperatingSystem::tryFrom($download->platform);
                $dir .= '/' . match ($os) {
                    OperatingSystem::Linux => 'linux',
                    OperatingSystem::MacOS => 'mac',
                    OperatingSystem::Windows => 'win',
                    default => throw new InvalidValueException("Unsupported operating system: {$download->platform}"),
                };
            }
        }

        $dir = "{$dir}/{$title}";
        if ($subdirectory !== null) {
            $dir .= "/{$subdirectory}";
        }

        return $dir;
    }
}
