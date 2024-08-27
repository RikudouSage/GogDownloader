<?php

namespace App\DTO\FileWriter;

use Aws\S3\S3Client;
use HashContext;

final class S3FileReference
{
    private int $partNumber = 0;
    private ?string $openedObjectId = null;
    private ?HashContext $hash = null;
    private ?S3Client $client = null;

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
            $this->hash = hash_init('md5');
        }
    }

    public function writeChunk(S3Client $client, string $data): void
    {
        $this->client = $client;
        $this->open($client);

        $client->uploadPart([
            'Body' => $data,
            'UploadId' => $this->openedObjectId,
            'Bucket' => $this->bucket,
            'Key' => $this->key,
            'PartNumber' => ++$this->partNumber,
        ]);
        hash_update($this->hash, $data);
    }

    public function __destruct()
    {
        if ($this->client !== null && $this->openedObjectId !== null && $this->hash !== null) {
            $this->client->completeMultipartUpload([
                'UploadId' => $this->openedObjectId,
                'Bucket' => $this->bucket,
                'Key' => $this->key,
            ]);

            $this->client->putObjectTagging([
                'Bucket' => $this->bucket,
                'Key' => $this->key,
                'Tagging' => [
                    'TagSet' => [
                        [
                            'Key' => 'md5_hash',
                            'Value' => hash_final($this->hash),
                        ],
                    ]
                ]
            ]);
        }
    }
}
