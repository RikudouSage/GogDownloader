<?php

namespace App\DTO;

final class GameInstaller implements DownloadableItem, PlatformSpecificItem
{
    public function __construct(
        public readonly string $language,
        public readonly string $platform,
        public readonly string $name,
        public readonly float  $size,
        public readonly string $url,
        public private(set) ?string $md5,
        public readonly ?int   $gogGameId,
        public readonly bool $isPatch = false,
    ) {
    }

    public function __unserialize(array $data): void
    {
        if (is_string($data['gogGameId'])) {
            $data['gogGameId'] = (int)$data['gogGameId'];
        }
        if (!isset($data['isPatch'])) {
            $data['isPatch'] = false;
        }

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
