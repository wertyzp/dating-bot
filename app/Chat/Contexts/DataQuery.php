<?php

declare(strict_types=1);

namespace App\Chat\Contexts;

use App\Chat\Contexts\Forms\CategoryForm;
use App\Chat\Contexts\Forms\ProductForm;
use Illuminate\Support\Facades\Validator;

class DataQuery
{
    protected const CLASS_MAP = [
        UpdateContext::class => 'update',
    ];

    public static function encode(array $callable, array $params = []): string
    {
        [$class] = $callable;
        $mapped = self::CLASS_MAP[$class] ?? $class;
        $callable[0] = $mapped;

        $data = implode('::', $callable);
        $callbackData = $data . '?'.http_build_query($params);
        if (strlen($callbackData) > 64) {
            throw new \InvalidArgumentException("Callback data is too long: $callbackData");
        }
        return $callbackData;
    }


    public static function decode(string $data): array
    {
        [$mapped, $methodWithParams] = explode('::', $data, 2);

        $map = array_flip(self::CLASS_MAP);
        $class = $map[$mapped] ?? $mapped;

        [$method, $paramsStr] = explode('?', $methodWithParams, 2);
        $params = [];
        parse_str($paramsStr, $params);
        return [[$class, $method], $params];
    }

}
