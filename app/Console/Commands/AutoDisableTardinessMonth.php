<?php

namespace App\Console\Commands;

use App\Models\TardinessDisabler;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoDisableTardinessMonth extends Command
{
    protected $signature = 'app:auto-disable-tardiness-month';

    protected $description = 'Automatically disables tardiness actions for the month two months prior.';

    public function handle()
    {
        $now = Carbon::now()->startOfMonth();
        $disableTarget = $now->copy()->subMonthsNoOverflow(2);

        TardinessDisabler::updateOrCreate(
            [
                'month' => (int) $now->month,
                'year' => (int) $now->year,
            ],
            [
                'is_disabled' => false,
            ]
        );

        TardinessDisabler::upsertDisabledForMonth($disableTarget);

        $this->info("Tardiness enabled for ".$now->format('F Y'));
        $this->info("Tardiness disabled for ".$disableTarget->format('F Y'));
    }
}
