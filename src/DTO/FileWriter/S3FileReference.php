<?php

namespace App\DTO\FileWriter;

use Aws\S3\S3Client;

final class S3FileReference
{
    private const MINIMUM_PART_SIZE = 10 * 1024 * 1024;

    private int $partNumber = 0;
    private ?string $openedObjectId = null;
    private ?S3Client $client = null;
    /** @var array<array{PartNumber: int, ETag: string}> */
    private array $parts = [];
    private string $buffer = '';

    public function __construct(
        public readonly string $bucket,
        public readonly string $key,
    ) {
    }

    public function open(S3Client $client): void
    {
        $this->client = $client;
        if ($this->openedObjectId === null) {
            $this->openedObjectId = $client->createMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
            ])->get('UploadId');
        }
    }

    public function writeChunk(S3Client $client, string $data, bool $forceWrite = false): void
    {
        $this->client = $client;

        if (strlen($this->buffer . $data) < self::MINIMUM_PART_SIZE && !$forceWrite) {
            $this->buffer .= $data;
            return;
        }
        $data = $this->buffer . $data;

        $this->open($client);

        $result = $client->uploadPart([
            'Body' => $data,
            'UploadId' => $this->openedObjectId,
            'Bucket' => $this->bucket,
            'Key' => $this->key,
            'PartNumber' => ++$this->partNumber,
        ]);
        $this->parts[] = [
            'PartNumber' => $this->partNumber,
            'ETag' => $result['ETag'],
        ];
        $this->buffer = '';
    }

    public function finalize(string $hash): void
    {
        if ($this->client !== null && $this->openedObjectId !== null  && count($this->parts)) {
            if ($this->buffer) {
                $this->writeChunk($this->client, $this->buffer, true);
            }

            $this->client->completeMultipartUpload([
                'UploadId' => $this->openedObjectId,
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'MultipartUpload' => [
                    'Parts' => $this->parts,
                ],
            ]);

            $this->client->putObjectTagging([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'Tagging' => [
                    'TagSet' => [
                        [
                            'Key' => 'md5_hash',
                            'Value' => $hash,
                        ],
                    ]
                ]
            ]);
        }
    }
}
