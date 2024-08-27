<?php

namespace App\Service\FileWriter;

use RuntimeException;

final readonly class FileWriterLocator
{
    /**
     * @param iterable<FileWriter> $writers
     */
    public function __construct(
        private iterable $writers,
    ) {
    }

    public function getWriter(string $directory): FileWriter
    {
        foreach ($this->writers as $writer) {
            if ($writer->supports($directory)) {
                return $writer;
            }
        }

        throw new RuntimeException("There's not handler that knows how to write to the directory '{$directory}'");
    }
}
