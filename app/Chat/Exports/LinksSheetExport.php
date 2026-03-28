<?php

declare(strict_types=1);

namespace App\Chat\Exports;

use App\Models\Link;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LinksSheetExport implements WithMultipleSheets
{
    protected Collection $types;
    public function __construct(protected int $chatId, protected \DateTimeImmutable $from, protected \DateTimeImmutable $to)
    {
        $this->types = Link::where('chat_id', $chatId)
            ->whereNotNull('message_id')
            ->whereExists(function ($query) {
                $query->select('id')
                    ->from('forwarded_messages')
                    ->whereColumn('forwarded_messages.source_message_id', 'links.message_id')
                    ->whereColumn('forwarded_messages.source_chat_id', 'links.chat_id');
            })
            ->distinct()
            ->pluck('type');
    }

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->types as $type) {
            $sheets[] = new LinksExport($this->chatId, $type, $this->from, $this->to);
        }
        return $sheets;
    }

}
