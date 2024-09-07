<?php

namespace App\Service\FileWriter;

use App\DTO\FileWriter\ExtractedS3Path;
use App\DTO\FileWriter\S3FileReference;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Stream;
use HashContext;

/**
 * @implements FileWriter<S3FileReference>
 */
final readonly class S3FileWriter implements FileWriter
{
    public function __construct(
        private S3Client $client,
    ) {
    }

    public function supports(string $path): bool
    {
        return str_starts_with($path, 's3://');
    }

    public function getFileReference(string $path): object
    {
        $extracted = $this->extractPath($path);

        return new S3FileReference($extracted->bucket, $extracted->key);
    }

    public function exists(object|string $file): bool
    {
        if (!is_object($file)) {
            $file = $this->getFileReference($file);
        }

        try {
            return $this->client->doesObjectExistV2($file->bucket, $file->key);
        } catch (S3Exception) {
            return false;
        }
    }

    public function getSize(object $file): int
    {
        $object = $this->client->headObject([
            'Bucket' => $file->bucket,
            'Key' => $file->key,
        ]);

        return $object->get('ContentLength');
    }

    public function getMd5Hash(object $file): string
    {
        $object = $this->client->headObject([
            'Bucket' => $file->bucket,
            'Key' => $file->key,
        ]);

        return array_find($object->get('Metadata'), function (string $value, string $key): string {
            return $key === 'md5_hash';
        }) ?? hash_final($this->getMd5HashContext($file));
    }

    public function createDirectory(string $path): void
    {
    }

    public function writeChunk(object $file, string $data, int $chunkSize = self::DEFAULT_CHUNK_SIZE): void
    {
        $file->writeChunk($this->client, $data, $chunkSize);
    }

    public function getMd5HashContext(object $file): HashContext
    {
        $hash = hash_init('md5');
        if (!$this->exists($file)) {
            return $hash;
        }

        $content = $this->client->getObject([
            'Bucket' => $file->bucket,
            'Key' => $file->key,
        ])->get('Body');
        assert($content instanceof Stream);

        $chunkSize = 2 ** 23;
        while (!$content->eof()) {
            hash_update($hash, $content->read($chunkSize));
        }

        return $hash;
    }

    private function extractPath(string $path): ExtractedS3Path
    {
        $path = substr($path, strlen('s3://'));
        $parts = explode('/', $path, 2);

        return new ExtractedS3Path($parts[0], $parts[1]);
    }

    public function finalizeWriting(object $file, string $hash): void
    {
        $file->finalize($hash);
    }

    public function remove(object $targetFile): void
    {
        $this->client->deleteObject([
            'Bucket' => $targetFile->bucket,
            'Key' => $targetFile->key,
        ]);
        $this->client->deleteObject([
            'Bucket' => $targetFile->bucket,
            'Key' => $targetFile->tempKey,
        ]);
    }
}
