<?php

namespace App\Jobs;

use App\Imports\OvertimeImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;

class ProcessOvertimeImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $overtimeFilePath;

    public function __construct(string $overtimeFilePath)
    {
        $this->overtimeFilePath = $overtimeFilePath;
    }

    public function handle(Excel $excel): void
    {
        $storage = Storage::disk("hr_system_folder");

        if ($storage->exists($this->overtimeFilePath)) {
            $filePath = $storage->path($this->overtimeFilePath);

            $excel->import(new OvertimeImport, $filePath);
        }
    }
}
