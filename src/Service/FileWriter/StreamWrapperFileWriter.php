<?php

namespace App\Service\FileWriter;

use App\DTO\FileWriter\StreamWrapperFileReference;
use App\Exception\UnreadableFileException;
use HashContext;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * @implements FileWriter<StreamWrapperFileReference>
 */
#[AsTaggedItem(priority: -10)]
final readonly class StreamWrapperFileWriter implements FileWriter
{
    public function supports(string $path): bool
    {
        $regex = /** @lang RegExp */ '@^([a-zA-Z0-9.]+)://.+@';
        if (!preg_match($regex, $path, $matches)) {
            return true;
        }
        $wrapper = $matches[1];
        $availableWrappers = stream_get_wrappers();

        return in_array($wrapper, $availableWrappers, true);
    }

    public function getFileReference(string $path): object
    {
        return new StreamWrapperFileReference($path);
    }

    public function exists(string|object $file): bool
    {
        if (!is_string($file)) {
            $file = $file->path;
        }

        return file_exists($file);
    }

    public function getSize(object $file): int
    {
        return filesize($file->path);
    }

    public function getMd5Hash(object $file): string
    {
        return md5_file($file->path);
    }

    public function writeChunk(object $file, string $data, int $chunkSize = self::DEFAULT_CHUNK_SIZE): void
    {
        $file->write($data);
    }

    public function createDirectory(string $path): void
    {
        mkdir($path, recursive: true);
    }

    public function getMd5HashContext(object $file): HashContext
    {
        if (!$this->isReadable($file)) {
            throw new UnreadableFileException('The file reference is not readable.');
        }
        $hash = hash_init('md5');
        if (!$this->exists($file)) {
            return $hash;
        }

        $handle = $file->open();
        rewind($handle);
        while (!feof($handle)) {
            hash_update($hash, fread($handle, 2 ** 23));
        }

        return $hash;
    }

    public function finalizeWriting(object $file, string $hash): void
    {
    }

    public function remove(object $targetFile): void
    {
        unlink($targetFile->path);
    }

    public function isReadable(object $targetFile): bool
    {
        if (!$this->exists($targetFile)) {
            return is_readable(dirname($targetFile->path));
        }

        return is_readable($targetFile->path);
    }
}
