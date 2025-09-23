<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        \App\Console\Commands\FetchBiotimeAttendance::class,
        \App\Console\Commands\FetchOvertimeInCSHub::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:fetch-biotime-attendance')->dailyAt("23:59:59");
        $schedule->command('app:fetch-overtime-in-cs-hub')->monthlyOn(11, '23:59:59');
        $schedule->command('app:fetch-overtime-in-cs-hub')->monthlyOn(26, '23:59:59');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
