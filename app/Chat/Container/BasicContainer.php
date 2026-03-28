<?php

declare(strict_types=1);

namespace App\Chat\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class BasicContainer implements ContainerInterface
{
    protected array $items = [];

    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new Exceptions\NotFoundException("Identifier $id is not defined");
        }
        return $this->items[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->items[$id]);
    }
}
