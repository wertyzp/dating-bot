<?php

declare(strict_types=1);

namespace App\Chat\ContextHandlers;

use App\Chat\Condition\Expression;
use App\Chat\Contexts\UpdateContext;

class FallbackContextHandler extends BasicContextHandler
{
    public function __construct()
    {
        $this->when(
            Expression::notEmpty('update_id'),
            [UpdateContext::class, 'update']
        );
    }
}
