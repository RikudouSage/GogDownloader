<?php

namespace App\DTO;

final class GameInstaller
{
    public function __construct(
        public readonly string $language,
        public readonly string $platform,
        public readonly string $name,
        public readonly float  $size,
        public readonly string $url,
        public private(set) ?string $md5,
        public readonly ?int   $gogGameId,
    ) {
    }

    public function __unserialize(array $data): void
    {
        if (is_string($data['gogGameId'])) {
            $data['gogGameId'] = (int)$data['gogGameId'];
        }

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
}
