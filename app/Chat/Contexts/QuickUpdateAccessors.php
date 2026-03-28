<?php

declare(strict_types=1);

namespace App\Chat\Contexts;

use App\Chat\Support\UpdateClient;
use Werty\Http\Clients\TelegramBot\Types\PhotoSize;

/**
 * @property-read UpdateClient $uc
 */
trait QuickUpdateAccessors
{
    protected function getMessageText(): string
    {
        return $this->uc->getUpdate()->getMessage()->getText();
    }

    /**
     * @return PhotoSize[]
     */
    protected function getMessagePhoto(): array
    {
        return $this->uc->getUpdate()->getMessage()->getPhoto() ?? [];
    }


}
