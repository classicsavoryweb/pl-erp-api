<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TardinessDisabler;
use Carbon\Carbon;

class TardinessDisablerSeeder extends Seeder
{
    public function run(): void
    {
        $start = Carbon::create(2020, 1, 1)->startOfMonth();
        $end = Carbon::create(2026, 1, 1)->startOfMonth();

        $current = $start->copy();
        while ($current->lte($end)) {
            $isEnabledException =
                ($current->year === 2025 && $current->month === 12) ||
                ($current->year === 2026 && $current->month === 1);

            $isDisabled = !$isEnabledException;

            TardinessDisabler::updateOrCreate(
                [
                    'month' => (int) $current->month,
                    'year' => (int) $current->year,
                ],
                [
                    'is_disabled' => $isDisabled,
                ]
            );

            $current->addMonthNoOverflow();
        }
    }
}
