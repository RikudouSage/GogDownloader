<?php

namespace App\Service\FileWriter;

use App\DTO\FileWriter\ExtractedS3Path;
use App\DTO\FileWriter\S3FileReference;
use App\Enum\S3StorageClass;
use App\Enum\Setting;
use App\Exception\InvalidConfigurationException;
use App\Exception\UnreadableFileException;
use App\Service\Persistence\PersistenceManager;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Stream;
use HashContext;
use LogicException;

/**
 * @implements FileWriter<S3FileReference>
 */
final readonly class S3FileWriter implements FileWriter
{
    public function __construct(
        private S3Client $client,
        private PersistenceManager $persistence,
    ) {
    }

    public function supports(string $path): bool
    {
        return str_starts_with($path, 's3://');
    }

    public function getFileReference(string $path): object
    {
        $extracted = $this->extractPath($path);

        $storageClassRaw = $this->persistence->getSetting(Setting::S3StorageClass);
        $storageClass = $storageClassRaw === null ? S3StorageClass::Standard : S3StorageClass::tryFrom($storageClassRaw);

        if (!$storageClass) {
            throw new InvalidConfigurationException("The configured storage class ({$storageClassRaw}) is not valid, please use one of: " . implode(
                ', ',
                    array_map(
                        fn (S3StorageClass $class) => $class->value,
                        S3StorageClass::cases(),
                    )
            ));
        }

        return new S3FileReference(
            $extracted->bucket,
            $extracted->key,
            $storageClass,
        );
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
        $object = $this->client->getObjectTagging([
            'Bucket' => $file->bucket,
            'Key' => $file->key,
        ]);

        return array_find($object->get('TagSet'), function (array $tag): bool {
            return $tag['Key'] === 'md5_hash';
        })['Value'] ?? hash_final($this->getMd5HashContext($file));
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
        if (!$this->isReadable($file)) {
            throw new UnreadableFileException('The file reference is not readable.');
        }

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
    }

    private function extractPath(string $path): ExtractedS3Path
    {
        $path = substr($path, strlen('s3://'));
        $parts = explode('/', $path, 2);

        return new ExtractedS3Path($parts[0], $parts[1]);
    }

    public function isReadable(object $targetFile): bool
    {
        if (!$this->exists($targetFile)) {
            return true;
        }

        $head = $this->client->headObject([
            'Bucket' => $targetFile->bucket,
            'Key' => $targetFile->key,
        ]);
        $storageClassName = $head->get('StorageClass') ?: S3StorageClass::Standard->value;
        $storageClass = S3StorageClass::tryFrom($storageClassName);
        if (!$storageClass) {
            throw new LogicException("The remote file '{$targetFile->key}' at bucket '{$targetFile->bucket}' has an unsupported storage class: {$storageClassName}");
        }

        return in_array($storageClass, [
            S3StorageClass::Standard,
            S3StorageClass::StandardInfrequentAccess,
            S3StorageClass::ExpressOneZone,
            S3StorageClass::StandardInfrequentAccess,
            S3StorageClass::OneZoneInfrequentAccess,
            S3StorageClass::GlacierInstantRetrieval,
        ], true);
    }
}
