<?php

declare(strict_types=1);

namespace App\Chat\Support;

use Werty\Http\Clients\TelegramBot\ModelBase;

class ModelCodec
{
    /**
     * Encode the given data.
     *
     * @param class-string $class
     * @param ModelBase $data
     * @return array
     */
    public static function encode(ModelBase $data): array
    {
        return $data->toArray();
    }

    public static function encodeArray(array $data): array
    {
        return array_map(fn($item) => $item->toArray(), $data);
    }

    public static function decodeArray(array $data, string $class): mixed
    {
        return array_map(fn($item) => new $class($item), $data);
    }

    public static function decode(array $data, string $class): mixed
    {
        return new $class($data);
    }
}
