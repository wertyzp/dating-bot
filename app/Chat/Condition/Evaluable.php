<?php

declare(strict_types=1);

namespace App\Chat\Condition;
interface Evaluable
{
    public function evaluate(array $input): bool;
    public function __toString(): string;
}
