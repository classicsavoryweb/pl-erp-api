<?php

namespace App\Exports\Maintenance;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Database\Eloquent\Collection;

class TrainingTitlesExport implements FromCollection, WithHeadings
{
    protected Collection $trainingTitles;

    public function __construct(Collection $trainingTitles)
    {
        $this->trainingTitles = $trainingTitles;
    }

    public function collection()
    {
        return $this->trainingTitles->map(function ($title) {
            return [
                $title->title,
                $title->option,
                $title->venue,
                $title->speaker,
                $title->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Title',
            'Option',
            'Venue',
            'Speaker',
            'Status',
        ];
    }
}
