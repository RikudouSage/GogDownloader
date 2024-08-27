<?php

namespace App\Service\FileWriter;

use HashContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template T of object
 */
#[AutoconfigureTag(name: 'app.file_writer')]
interface FileWriter
{
    public function supports(string $path): bool;

    /**
     * @return T
     */
    public function getFileReference(string $path): object;

    /**
     * @param string|T $file
     */
    public function exists(string|object $file): bool;

    /**
     * @param T $file
     */
    public function getSize(object $file): int;

    /**
     * @param T $file
     */
    public function getMd5Hash(object $file): string;
    public function createDirectory(string $path): void;

    /**
     * @param T $file
     */
    public function writeChunk(object $file, string $data): void;

    /**
     * @param T $file
     */
    public function getMd5HashContext(object $file): HashContext;

    /**
     * @param T $file
     */
    public function finalizeWriting(object $file, string $hash): void;
}
