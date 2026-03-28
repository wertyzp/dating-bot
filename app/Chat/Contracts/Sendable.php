<?php

declare(strict_types=1);

namespace App\Chat\Contracts;

interface Sendable
{
    public function getText(): string;
    public function getKeyboard(): array;
}
