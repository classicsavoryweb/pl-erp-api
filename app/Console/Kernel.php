<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        \App\Console\Commands\FetchBiotimeAttendance::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:fetch-biotime-attendance')->dailyAt("23:30:00");
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
