<?php

declare(strict_types=1);

namespace App\Chat\Condition;

use App\Chat\Contracts\ArrayMorphs;

abstract class ArrayMorphedEvaluable implements Evaluable, ArrayMorphs
{
    public static function fromArray(array $data): ArrayMorphedEvaluable
    {
        $class = array_shift($data);
        if (!is_subclass_of($class, self::class)) {
            throw new \InvalidArgumentException("Invalid class $class");
        }
        if ($class === self::class) {
            return new static();
        }
        return $class::fromArray($data);
    }
}
