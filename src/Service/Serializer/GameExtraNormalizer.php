<?php

namespace App\Service\Serializer;

use App\DTO\GameExtra;
use App\DTO\MultipleValuesWrapper;
use App\Service\OwnedItemsManager;
use App\Service\Serializer;
use ReflectionProperty;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Response;

final readonly class GameExtraNormalizer implements SerializerNormalizer
{
    public function __construct(
        private OwnedItemsManager $ownedItemsManager,
    ) {
    }

    public function normalize(array $value, array $context, Serializer $serializer): MultipleValuesWrapper|GameExtra
    {
        if (isset($value['total_size'])) {
            $result = [];
            foreach ($value['files'] as $file) {
                $extra = new GameExtra(
                    id: $value['id'],
                    name: $value['name'],
                    size: $file['size'],
                    url: $file['downlink'],
                    gogGameId: $value['gogGameId'],
                    md5: null,
                );
                try {
                    $md5 = $this->ownedItemsManager->getChecksum($extra, null, 10);
                    new ReflectionProperty($extra, 'md5')->setValue($extra, $md5);
                } catch (ClientException $e) {
                    if ($e->getCode() !== Response::HTTP_FORBIDDEN && $e->getCode() !== Response::HTTP_NOT_FOUND) {
                        throw $e;
                    }
                    continue;
                }
                $result[] = $extra;
            }

            return new MultipleValuesWrapper($result);
        }

        return new GameExtra(
            id: $value['extra_id'],
            name: $value['name'],
            size: $value['size'],
            url: $value['url'],
            gogGameId: $value['gog_game_id'],
            md5: $value['md5'],
        );
    }

    public function supports(string $class): bool
    {
        return is_a($class, GameExtra::class, true);
    }
}
