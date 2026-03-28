<?php

declare(strict_types=1);

namespace App\Chat\Contracts;

interface ArrayMorphs
{
    public function toArray(): array;
    public static function fromArray(array $data): self;
}
