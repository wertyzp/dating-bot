<?php

declare(strict_types=1);

namespace App\Chat\Exports;

use App\Models\Link;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LinksExport implements FromGenerator, WithTitle, ShouldAutoSize, WithHeadings
{
    protected Builder $builder;

    public function __construct(protected int $chatId, protected string $type, protected \DateTimeImmutable $from, protected \DateTimeImmutable $to)
    {
        $this->builder = Link::where('chat_id', $chatId)
                ->whereNotNull('message_id')
                ->where('type', $type)
                ->where('created_at', '>=', $from)
                ->where('created_at', '<=', $to)
                ->where('in_links_report', true)
                ->whereExists(function ($query) {
            $query->select('id')
                ->from('forwarded_messages')
                ->whereColumn('forwarded_messages.source_message_id', 'links.message_id')
                ->whereColumn('forwarded_messages.source_chat_id', 'links.chat_id');
        });
    }

    public function generator(): \Generator
    {
        // get all links
        foreach ($this->builder->cursor() as $item) {
            $result = [
                $item->created_at->format('Y-m-d H:i:s'),
                $item->link,
            ];
            if ($item->data) {
                $result = array_merge($result, $item->data);
            }
            yield $result;
        }
    }

    public function headings(): array
    {
        /**
         * "ups": 8,
         * "downs": 0,
         * "score": 8,
         * "title": "Join the Ruvi AI Ecosystem: Where Everyone Benefits from AI Growth",
         * "author": "marv_lous",
         * "created": "2025-04-04 06:49:57",
         * "crosspost": false,
         * "num_comments": 5,
         * "upvote_ratio": 1
         */
        return [
            'Date',
            'Link',
            'Ups',
            'Downs',
            'Score',
            'Title',
            'Author',
            'Created',
            'Crosspost',
            'Num Comments',
            'Upvote Ratio',
        ];
    }

    public function title(): string
    {
        return $this->type;
    }

}
