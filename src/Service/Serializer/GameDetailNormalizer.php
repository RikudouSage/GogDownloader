<?php

namespace App\Service\Serializer;

use App\DTO\DownloadDescription;
use App\DTO\GameDetail;
use App\DTO\MultipleValuesWrapper;
use App\Helper\LatelyBoundStringValue;
use App\Service\DownloadManager;
use App\Service\Serializer;
use Error;

final readonly class GameDetailNormalizer implements SerializerNormalizer
{
    public function __construct(
        private DownloadManager $downloadManager,
    ) {
    }

    public function normalize(array $value, array $context, Serializer $serializer): GameDetail
    {
        $downloads = [];
        foreach ($value['downloads'] as $download) {
            if (!isset($value['id'])) { // this is not an existing, saved download
                $download['gogGameId'] = $context['id'];
            }
            $deserialized = $serializer->deserialize($download, DownloadDescription::class);

            $downloads[] = $deserialized;
        }
        foreach ($value['dlcs'] ?? [] as $dlc) {
            foreach ($dlc['downloads'] as $download) {
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

        foreach ($finalDownloads as $index => $finalDownload) {
            if ($finalDownload->gogGameId) {
                continue;
            }
            $id = new LatelyBoundStringValue(function () use ($finalDownload): string {
                return $this->downloadManager->getGameId($finalDownload) ?? '';
            });
            try {
                $md5 = $finalDownload->md5;
            } catch (Error) {
                $md5 = null;
            }
            $finalDownloads[$index] = new DownloadDescription(
                language: $finalDownload->language,
                platform: $finalDownload->platform,
                name: $finalDownload->name,
                size: $finalDownload->size,
                url: $finalDownload->url,
                md5: $md5,
                gogGameId: $id,
            );
        }

        return new GameDetail(
            id: $value['id'] ?? $context['id'],
            title: $value['title'],
            cdKey: $value['cdKey'],
            downloads: $finalDownloads,
            slug: $value['slug'] ?? $context['slug'],
        );
    }

    public function supports(string $class): bool
    {
        return is_a($class, GameDetail::class, true);
    }
}
