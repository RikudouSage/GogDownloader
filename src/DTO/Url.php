<?php

namespace App\DTO;

final class Url
{
    public function __construct(
        private readonly ?string $scheme = null,
        private readonly ?string $host = null,
        private readonly ?string $path = null,
        private readonly array $query = [],
    ) {
    }

    public function __toString(): string
    {
        $url = '';
        if ($this->scheme !== null) {
            $url .= "{$this->scheme}://";
        }
        if ($this->host !== null) {
            $url .= $this->host;
        }
        if ($this->path !== null) {
            $url .= "/{$this->path}";
        }
        if (count($this->query)) {
            $url .= sprintf('?%s', http_build_query($this->query));
        }

        return $url;
    }
}
