<?php

declare(strict_types=1);

namespace App\Chat\Contexts\Forms;

use App\Chat\Support\UpdateClient;

abstract class Form
{
    protected string $id;
    protected array $keyMap = [];
    public function __construct()
    {
        $this->id = uniqid('', true);
    }

    public function getId(): string
    {
        return $this->id;
    }
    abstract public function render(UpdateClient $uc): bool;
    abstract public function update();

    protected function resetKeyboard(): void
    {
        $this->keyMap = [];
    }
    protected function createKey(mixed $value): string
    {
        $keyCode = uniqid();
        $this->setKey($keyCode, $value);
        return $keyCode;
    }
    protected function setKey(string $keyCode, mixed $value): void
    {
        $this->keyMap[$keyCode] = $value;
    }

    protected function getKey(string $keyCode): mixed
    {
        return $this->keyMap[$keyCode] ?? null;
    }

    abstract public function handleKey(string $keyCode): void;
}
