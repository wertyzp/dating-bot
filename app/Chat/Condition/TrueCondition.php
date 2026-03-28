<?php

declare(strict_types=1);

namespace App\Chat\Condition;

class TrueCondition extends ArrayMorphedEvaluable
{
    public function evaluate(array $data): bool
    {
        return true;
    }

    public function toArray(): array
    {
        return [
            self::class,
        ];
    }

    public function __toString(): string
    {
        return 'true';
    }
}
