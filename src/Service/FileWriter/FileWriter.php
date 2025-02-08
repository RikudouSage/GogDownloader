<?php

namespace App\Service\FileWriter;

use App\Exception\UnreadableFileException;
use HashContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @template T of object
 */
#[AutoconfigureTag(name: 'app.file_writer')]
interface FileWriter
{
    public const DEFAULT_CHUNK_SIZE = 2 ** 23;

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
     * @throws UnreadableFileException
     */
    public function getMd5Hash(object $file): string;

    public function createDirectory(string $path): void;

    /**
     * @param T $file
     */
    public function writeChunk(object $file, string $data, int $chunkSize = self::DEFAULT_CHUNK_SIZE): void;

    /**
     * @param T $file
     * @throws UnreadableFileException
     */
    public function getMd5HashContext(object $file): HashContext;

    /**
     * @param T $file
     */
    public function finalizeWriting(object $file, string $hash): void;

    /**
     * @param T $targetFile
     */
    public function remove(object $targetFile): void;

    /**
     * @param T $targetFile
     */
    public function isReadable(object $targetFile): bool;
}
