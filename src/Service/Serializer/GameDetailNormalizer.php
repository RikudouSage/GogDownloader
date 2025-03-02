<?php

namespace App\Service\Serializer;

use App\DTO\DownloadDescription;
use App\DTO\GameDetail;
use App\DTO\MultipleValuesWrapper;
use App\Service\Serializer;

final readonly class GameDetailNormalizer implements SerializerNormalizer
{
    public function normalize(array $value, array $context, Serializer $serializer): GameDetail
    {
        if (!isset($value['downloads']['installers'])) {
            $finalDownloads = array_map(
                fn (array $download) => $serializer->deserialize($download, DownloadDescription::class),
                $value['downloads'],
            );
        } else {
            $sourceDownloads = [
                ...$value['downloads']['installers'],
                ...$value['downloads']['patches'],
                ...$value['downloads']['language_packs'],
            ];
            $downloads = [];
            foreach ($sourceDownloads as $download) {
                $download['gogGameId'] = $context['id'];
                $downloads[] = $serializer->deserialize($download, DownloadDescription::class);
            }
            foreach ($value['expanded_dlcs'] ?? [] as $dlc) {
                foreach ($dlc['downloads']['installers'] as $download) {
                    $download['gogGameId'] = $dlc['id'];
                    $downloads[] = $serializer->deserialize($download, DownloadDescription::class);
                }
            }

            $finalDownloads = [];
            foreach ($downloads as $download) {
                if ($download instanceof MultipleValuesWrapper) {
                    $finalDownloads = [...$finalDownloads, ...$download];
                } else {
                    $finalDownloads[] = $download;
                }
            }
        }

        return new GameDetail(
            id: $value['id'] ?? $context['id'],
            title: $value['title'],
            cdKey: '', // todo
            downloads: $finalDownloads,
            slug: $value['slug'] ?? $context['slug'],
        );
    }

    public function supports(string $class): bool
    {
        return is_a($class, GameDetail::class, true);
    }
}
