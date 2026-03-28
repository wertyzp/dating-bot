<?php

declare(strict_types=1);

namespace App\Chat\Contexts;

use App\Chat\Contracts\ContextHandler;

abstract class BaseContext implements Context
{
    public function run(string $method, array $params = []): ?ContextHandler
    {
        if (!method_exists($this, $method)) {
            return throw new \BadMethodCallException("Method $method not found");
        }
        $this->setup($params);
        $result = $this->$method();
        $this->teardown();

        return $result;
    }
}
