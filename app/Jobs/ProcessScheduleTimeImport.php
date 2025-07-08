<?php

namespace App\Jobs;

use App\Imports\ScheduleTimeImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;

class ProcessScheduleTimeImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $scheduleTimeFilePath;

    public function __construct(string $scheduleTimeFilePath)
    {
        $this->scheduleTimeFilePath = $scheduleTimeFilePath;
    }

    public function handle(Excel $excel): void
    {
        $storage = Storage::disk("hr_system_folder");

        if ($storage->exists($this->scheduleTimeFilePath)) {
            $filePath = $storage->path($this->scheduleTimeFilePath);

            $excel->import(new ScheduleTimeImport, $filePath);
        }
    }
}
