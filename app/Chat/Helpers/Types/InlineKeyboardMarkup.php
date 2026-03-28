<?php

declare(strict_types=1);

namespace App\Chat\Helpers\Types;

use App\Chat\Contexts\DataQuery;
use App\Chat\Contexts\UpdateContext;
use Werty\Http\Clients\TelegramBot\Types\InlineKeyboardButton;

class InlineKeyboardMarkup extends \Werty\Http\Clients\TelegramBot\Types\InlineKeyboardMarkup
{
    public static function easy(array $keyboard, array $appendParams = []): InlineKeyboardMarkup
    {
        $result = [];
        foreach ($keyboard as $row) {
            $newRow = [];
            foreach ($row as $button) {
                [$text, $callable, $params] = $button + [null, null, []];
                $params = array_merge($params, $appendParams);
                $newRow[] = ['text' => $text, 'callback_data' => DataQuery::encode($callable, $params)];
            }
            $result[] = $newRow;
        }

        return new self([
            'inline_keyboard' => $result,
        ]);
    }

    public static function forForm(array $keyboard, string $formId): InlineKeyboardMarkup
    {
        $result = [];
        foreach ($keyboard as $row) {
            $newRow = [];
            foreach ($row as $button) {
                $text = reset($button);
                $keyCode = next($button);
                if ($keyCode === false) {
                    $keyCode = 0;
                }

                $params['fid'] = $formId;
                $params['kc'] = $keyCode;
                $callable = [UpdateContext::class, 'updateForm'];
                $newRow[] = ['text' => $text, 'callback_data' => DataQuery::encode($callable, $params)];
            }
            $result[] = $newRow;
        }

        return new self([
            'inline_keyboard' => $result,
        ]);
    }

    public function appendToRow(int $index, InlineKeyboardButton $button): void
    {
        $keyboard = $this->inline_keyboard;
        $keyboard[$index][] = $button;
        $this->inline_keyboard = $keyboard;
    }
}
