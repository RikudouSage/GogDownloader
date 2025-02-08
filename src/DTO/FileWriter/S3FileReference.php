<?php

namespace App\DTO\FileWriter;

use App\Enum\S3StorageClass;
use Aws\S3\S3Client;

final class S3FileReference
{
    private const DEFAULT_CHUNK_SIZE = 10 * 1024 * 1024;

    private int $partNumber = 0;

    private ?string $openedObjectId = null;

    private ?S3Client $client = null;

    /** @var array<array{PartNumber: int, ETag: string}> */
    private array $parts = [];

    private string $buffer = '';

    private int $chunkSize = 0;

    public function __construct(
        public readonly string $bucket,
        public readonly string $key,
        public readonly S3StorageClass $requestedStorageClass,
    ) {
    }

    public function __destruct()
    {
        if ($this->client !== null && $this->openedObjectId !== null) {
            $this->client->abortMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'UploadId' => $this->openedObjectId,
            ]);
        }
    }

    public function open(S3Client $client): void
    {
        $this->client = $client;
        if ($this->openedObjectId === null) {
            $this->openedObjectId = $client->createMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'StorageClass' => $this->requestedStorageClass->value,
            ])->get('UploadId');
        }
    }

    public function writeChunk(S3Client $client, string $data, int $chunkSize, bool $forceWrite = false): void
    {
        $this->client = $client;
        $this->chunkSize = $chunkSize;

        if (strlen($this->buffer . $data) < $chunkSize && !$forceWrite) {
            $this->buffer .= $data;

            return;
        }
        $data = $this->buffer . $data;

        $this->open($client);

        $result = $client->uploadPart([
            'Body' => substr($data, 0, $chunkSize),
            'UploadId' => $this->openedObjectId,
            'Bucket' => $this->bucket,
            'Key' => $this->key,
            'PartNumber' => ++$this->partNumber,
        ]);
        $this->parts[] = [
            'PartNumber' => $this->partNumber,
            'ETag' => $result['ETag'],
        ];
        $this->buffer = substr($data, $chunkSize);
    }

    public function finalize(string $hash): void
    {
        if ($this->client !== null && (count($this->parts) || $this->buffer)) {
            while ($this->buffer) {
                $this->writeChunk($this->client, $this->buffer, $this->chunkSize ?: self::DEFAULT_CHUNK_SIZE, true);
            }

            $this->client->completeMultipartUpload([
                'UploadId' => $this->openedObjectId,
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'MultipartUpload' => [
                    'Parts' => $this->parts,
                ],
            ]);
            $this->openedObjectId = null;

            while (!$this->client->doesObjectExistV2($this->bucket, $this->key)) {
                sleep(1);
            }

            $this->client->putObjectTagging([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'Tagging' => [
                    'TagSet' => [
                        [
                            'Key' => 'md5_hash',
                            'Value' => $hash,
                        ],
                    ],
                ]
            ]);
        }
    }
}
