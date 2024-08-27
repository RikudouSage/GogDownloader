<?php

namespace App\DTO\FileWriter;

use Aws\S3\S3Client;

final class S3FileReference
{
    private const PART_SIZE = 10 * 1024 * 1024;

    private int $partNumber = 0;
    private ?string $openedObjectId = null;
    private ?S3Client $client = null;
    /** @var array<array{PartNumber: int, ETag: string}> */
    private array $parts = [];
    private string $buffer = '';

    private readonly string $tempKey;

    public function __construct(
        public readonly string $bucket,
        public readonly string $key,
    ) {
        $this->tempKey = $this->key . '.gog-downloader.tmp';
    }

    public function open(S3Client $client): void
    {
        $this->client = $client;
        if ($this->openedObjectId === null) {
            $this->openedObjectId = $client->createMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $this->tempKey,
            ])->get('UploadId');
        }
    }

    public function writeChunk(S3Client $client, string $data, bool $forceWrite = false): void
    {
        $this->client = $client;

        if (strlen($this->buffer . $data) < self::PART_SIZE && !$forceWrite) {
            $this->buffer .= $data;
            return;
        }
        $data = $this->buffer . $data;

        $this->open($client);

        $result = $client->uploadPart([
            'Body' => substr($data, 0, self::PART_SIZE),
            'UploadId' => $this->openedObjectId,
            'Bucket' => $this->bucket,
            'Key' => $this->tempKey,
            'PartNumber' => ++$this->partNumber,
        ]);
        $this->parts[] = [
            'PartNumber' => $this->partNumber,
            'ETag' => $result['ETag'],
        ];
        $this->buffer = substr($data, self::PART_SIZE);
    }

    public function finalize(string $hash): void
    {
        if ($this->client !== null && $this->openedObjectId !== null  && count($this->parts)) {
            while ($this->buffer) {
                $this->writeChunk($this->client, $this->buffer, true);
            }

            $this->client->completeMultipartUpload([
                'UploadId' => $this->openedObjectId,
                'Bucket' => $this->bucket,
                'Key' => $this->tempKey,
                'MultipartUpload' => [
                    'Parts' => $this->parts,
                ],
            ]);

            while (!$this->client->doesObjectExistV2($this->bucket, $this->tempKey)) {
                sleep(1);
            }

            $this->client->copyObject([
                'Key' => $this->key,
                'Bucket' => $this->bucket,
                'CopySource' => "{$this->bucket}/{$this->tempKey}",
                'MetadataDirective' => 'REPLACE',
                'Metadata' => [
                    'md5_hash' => $hash,
                ],
            ]);
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $this->tempKey,
            ]);
        }
    }
}
