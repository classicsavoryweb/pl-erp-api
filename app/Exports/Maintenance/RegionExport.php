<?php

namespace App\Exports\Maintenance;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class RegionExport implements FromView
{
    protected $regions;

    public function __construct($regions)
    {
        $this->regions = $regions;
    }

    public function view(): View
    {
        return view('exports.maintenance.region-export', [
            'regions' => $this->regions,
        ]);
    }
}
