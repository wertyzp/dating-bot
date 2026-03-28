<?php

declare(strict_types=1);

namespace App\Chat\Contexts\Exceptions;

class Exception extends \Exception
{
    public function __construct(protected string $userFriendlyMessage)
    {
        parent::__construct('Context setup error');
    }

    public function getUserFriendlyMessage(): string
    {
        return $this->userFriendlyMessage;
    }
}
