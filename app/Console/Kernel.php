<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected $commands = [
        \App\Console\Commands\FetchBiotimeAttendance::class,
        \App\Console\Commands\FetchOvertimeInCSHub::class,
        \App\Console\Commands\FetchEmployeeAssignedSchedule::class,
        \App\Console\Commands\SyncEmployeeDetails::class,
        \App\Console\Commands\TransferUniformDeductions::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:fetch-biotime-attendance')->dailyAt("23:59");
        $schedule->command('app:fetch-overtime-in-cs-hub')->monthlyOn(11, '21:00');
        $schedule->command('app:fetch-overtime-in-cs-hub')->monthlyOn(26, '21:00');
        $schedule->command('app:fetch-employee-assigned-schedule')->monthlyOn(10, '14:01');
        $schedule->command('app:fetch-employee-assigned-schedule')->monthlyOn(25, '14:01');
        $schedule->command('app:sync-employee-details')->dailyAt('12:00');
        $schedule->command('app:sync-employee-details')->dailyAt('18:00');
        $schedule->command('app:transfer-uniform-deductions')->dailyAt('12:00');
        $schedule->command('app:transfer-uniform-deductions')->dailyAt('18:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
