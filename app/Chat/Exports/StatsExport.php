<?php

declare(strict_types=1);

namespace App\Chat\Exports;

use App\Models\Chat;
use App\Models\Link;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StatsExport implements WithMultipleSheets
{
    protected Collection $employees;
    public function __construct(protected int $chatId, protected \DateTimeImmutable $from, protected \DateTimeImmutable $to)
    {
        $this->employees = Chat::find($chatId)->employees;
    }

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->employees as $employee) {
            $sheets[] = new StatsSheetExport($employee->id, $this->from, $this->to);
        }
        return $sheets;
    }

}
