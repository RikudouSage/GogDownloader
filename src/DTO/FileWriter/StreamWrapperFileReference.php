<?php

namespace App\DTO\FileWriter;

final class StreamWrapperFileReference
{
    /**
     * @var resource|null
     */
    private $fileHandle = null;

    public function __construct(
        public readonly string $path,
    ) {
    }

    public function __destruct()
    {
        if (is_resource($this->fileHandle)) {
            fclose($this->fileHandle);
        }
    }

    public function write(string $data): void
    {
        fwrite($this->open(), $data);
    }

    /**
     * @return resource
     */
    public function open()
    {
        if ($this->fileHandle === null) {
            $mode = file_exists($this->path) ? 'a+' : 'w+';
            if (!is_dir(dirname($this->path))) {
                mkdir(dirname($this->path), 0777, true);
            }
            $this->fileHandle = fopen($this->path, $mode);
        }

        return $this->fileHandle;
    }
}
