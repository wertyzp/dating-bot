<?php

declare(strict_types=1);

namespace App\Chat\Exports;

use App\Models\Employee;
use App\Models\Link;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StatsSheetExport implements FromCollection, WithTitle, WithEvents
{
    protected string $query;
    protected array $bindings;
    protected string $name;
    protected array $columnMerges = [];
    protected array $outlinedBlocks = [];
    protected array $boldCells = [];
    protected array $cellColors = [];
    protected array $coloredFontCells = [];

    protected array $tooltips = [];

    protected Employee $employee;
    public function __construct(protected int $employeeId, protected \DateTimeImmutable $from, protected \DateTimeImmutable $to)
    {
        /** @var Employee $employee */
        $employee = Employee::find($employeeId);
        if (!$employee) {
            throw new \InvalidArgumentException('Employee not found');
        }
        $this->employee = $employee;
        $this->query =<<<EOL
SELECT
    DATE(links.created_at) AS date_created,
    DATE_FORMAT(links.created_at, '%Y-%m-%d %H:00:00') AS datehour_created,
    DATE_FORMAT(links.created_at, '%H:00') AS date_hour,
    GROUP_CONCAT(links.link SEPARATOR '\n') AS links,
    CASE
        WHEN links.is_daily = 1 THEN CONCAT('daily ', links.type)
        ELSE links.type
    END AS ratio_type,
    COUNT(links.link) AS total_links
FROM links
WHERE links.chat_id = :chat_id
  AND links.user_id = :user_id
  AND links.in_links_report = 1
  AND links.message_id IS NOT NULL
  AND EXISTS (
      SELECT 1
      FROM forwarded_messages
      WHERE forwarded_messages.source_message_id = links.message_id
        AND forwarded_messages.source_chat_id = links.chat_id
  )
  AND links.created_at BETWEEN :from AND :to
GROUP BY date_created, datehour_created, date_hour, ratio_type
ORDER BY date_created, datehour_created ASC;
EOL;
        $this->bindings = [
            'chat_id' => $employee->chat_id,
            'user_id' => $employee->user_id,
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->format('Y-m-d'),
        ];

        $this->name = $employee->user->getName();
    }

    public function title(): string
    {
        return $this->name;
    }

    public function collection()
    {
        $data = DB::select($this->query, $this->bindings);

        if (empty($data)) {
            return collect([]);
        }

        $chatRatios = $this->employee->chat->setup?->ratios;
        if ($chatRatios) {
            $ratiosByHourIndex = $this->employee->chat->setup->ratios->groupBy('hour')->sortKeys()->toArray();
        } else {
            $ratiosByHourIndex = [];
        }
        $employeeRatiosByHourIndex = $this->employee->ratios->groupBy('hour')->sortKeys()->toArray();
        // override chat ratios with employee ratios
        foreach ($ratiosByHourIndex as $hour => $ratios) {
            if (isset($employeeRatiosByHourIndex[$hour])) {
                $ratiosByHourIndex[$hour] = $ratios;
            }
        }

        $ratioTypesMap = [];
        foreach($ratiosByHourIndex as $ratios) {
            foreach ($ratios as $ratio) {
                $ratioTypesMap[$ratio['type']] = true;
            }
        }

        // get from ratiosByHourIndex all types
        $ratioTypes = collect(array_keys($ratioTypesMap))->unique()->sort();
        // hour to index map
        $hourIndexMap = [];
        $thisTime = $this->employee->start_time;
        $endTime = $this->employee->end_time;
        if ($endTime->lessThan($thisTime)) {
            $endTime->addDay();
        }
        $i = 1;
        while ($thisTime->lessThan($endTime)) {
            $hourIndexMap[$thisTime->format('H:00')] = $i;
            $i++;
            $thisTime->addHour();
        }

        Log::info("hourIndexMap", [
            'hourIndexMap' => $hourIndexMap,
            'ratiosByHourIndex' => $ratiosByHourIndex,
        ]);

        $data = collect($data);

        $data = $data->groupBy('date_created');
        $result = collect();

        foreach ($data as $date => $item) {
            $result[] = [
                $date
            ];

            $types = $item->pluck('ratio_type')->merge($ratioTypes)->unique()->sort();

            $hourGroupedData = $item->groupBy('datehour_created')->sortBy('datehour_created');
            $row = array_merge(["Hour",],
                $types->toArray(),
                ['SUM']
            );
            // make sum bold

            $this->columnMerges[] = [1, count($result), count($row), count($result)];
            $outlineBlock = [1, count($result)];

            $result[] = $row;
            $this->boldCells[] = [count($row), count($result), count($row), count($result)];

            $dateHourIndexMap = $hourIndexMap;
            // we need to insert hours that has any links activity
            // but not in the data
            foreach ($hourGroupedData as $hourItem) {
                $hour = $hourItem->first()->date_hour;
                if (!isset($dateHourIndexMap[$hour])) {
                    $dateHourIndexMap[$hour] = null; // no ratio index
                }
            }
            // now sort the array by hour
            $dateHourIndexMap = collect($dateHourIndexMap)->sortKeys();

            //foreach ($hourGroupedData as $hourItem) {
            foreach ($dateHourIndexMap as $hour => $ratioIndex) {
                $res = [
                    $hour
                ];

                $ratiosByCurrentHour = [];
                $hourIndex = $hourIndexMap[$hour] ?? null;
                if ($hourIndex) {
                    $this->cellColors[] = [
                        // hour cell + current cell count
                        [count($res), count($result) + 1, count($res), count($result) + 1],
                        // light purple
                        'd9d9ff'
                    ];
                    if (isset($ratiosByHourIndex[$hourIndex])) {
                        $ratiosByCurrentHour = array_column($ratiosByHourIndex[$hourIndex], 'expected_count', 'type');
                    }
                }

                $hourItem = $hourGroupedData->get("$date $hour:00");

                if (empty($ratiosByCurrentHour) && !$hourItem) {
                    // no ratios for current our, no activity during this hour
                    $result[] = $res;
                    continue;
                }

                $resultsByType = [];

                if ($hourItem) {
                    $byKey = $hourItem->keyBy('ratio_type');
                    foreach ($types as $type) {
                        $resultsByType[] = [$type, $byKey[$type]?->total_links ?? 0, $byKey[$type]?->links ?? null];
                    }
                } else {
                    foreach ($types as $type) {
                        $resultsByType[$type] = [$type, 0, null];
                    }
                }
                $ratiosNotMet = false;
                foreach ($resultsByType as [$type, $totalLinks, $tooltip]) {
                    $res[] = "$totalLinks";
                    if ($tooltip) {
                        $this->tooltips[] = [
                            [count($res), count($result) + 1, count($res), count($result) + 1],
                            $tooltip
                        ];
                    }
                    $expectedCount = $ratiosByCurrentHour[$type] ?? 0;
                    if ($expectedCount <= 0) {
                        continue;
                    }
                    // if $totalLinks >= $expectedCount set cell to bbf5ba
                    $k = $totalLinks / $expectedCount;
                    $color = 'fa8c8c'; //  red
                    if ($k == 1) {
                        $color = 'bbf5ba'; // green
                    }
                    elseif ($k > 1) {
                        $color = 'ecfa82'; // yellow
                    } else {
                        $ratiosNotMet = true;
                    }
                    $this->cellColors[] = [
                        // hour cell + current cell count
                        [count($res), count($result) + 1, count($res), count($result) + 1],
                        $color
                    ];
                }
                $res[] = $hourItem?->sum('total_links') ?? '0';
                if ($ratiosNotMet) {
                    $this->coloredFontCells[] = [
                        [count($res), count($result) + 1, count($res), count($result) + 1],
                        'FFFF0000' // Red font color
                    ];
                }
                $result[] = $res;
            }
            $outlineBlock[] = count($row);
            $outlineBlock[] = count($result);
            $this->outlinedBlocks[] = $outlineBlock;

            $result[] = [
                "",
            ];
        }
        return $result;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getDefaultColumnDimension()->setWidth(15);

                $boldCentered = [
                    'font' => [
                        'bold' => true,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'argb' => 'FFCCCCCC',
                        ],
                    ],
                ];

                foreach($this->columnMerges as $merge) {
                    $sheet->mergeCells($merge);
                    $style = $sheet->getStyle($merge);
                    $style->applyFromArray($boldCentered);
                }

                $allBorders = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ];

                $boldColoredFont = [
                    'font' => [
                        'bold' => true,
                        'color' => [
                            'argb' => 'FF000000',
                        ],
                    ],
                ];

                foreach ($this->outlinedBlocks as $block) {
                    $style = $sheet->getStyle($block);
                    $style->applyFromArray($allBorders);
                }
                $bold = [
                    'font' => [
                        'bold' => true,
                    ],
                ];
                foreach ($this->boldCells as $boldCell) {
                    $style = $sheet->getStyle($boldCell);
                    $style->applyFromArray($bold);
                }

                foreach ($this->cellColors as $cellData) {
                    [
                        $cell,
                        $color
                    ] = $cellData;
                    $style = $sheet->getStyle($cell);
                    $style->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->setStartColor(new Color("ff$color"));
                }

                foreach ($this->tooltips as $tooltip) {
                    [
                        $cell,
                        $text
                    ] = $tooltip;
                    $sheet->getComment($cell)->getText()->createTextRun($text);
                    $sheet->getComment($cell)->setWidth('300');
                    $sheet->getComment($cell)->setHeight('100');
                    $sheet->getComment($cell)->setVisible(false);
                }

                foreach ($this->coloredFontCells as $cellData) {
                    [
                        $cell,
                        $color
                    ] = $cellData;
                    $style = $sheet->getStyle($cell);
                    $thisCellFontColorStyle = $boldColoredFont;
                    $thisCellFontColorStyle['font']['color']['argb'] = $color;
                    $style->applyFromArray($thisCellFontColorStyle);
                }
            },
        ];
    }
}
