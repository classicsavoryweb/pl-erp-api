<?php

namespace App\Jobs;

use App\Imports\ActualLogsImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;

class ProcessActualTimeImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $userId;

    public function __construct(string $filePath, ?int $userId = null)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(Excel $excel): void
    {
        $storage = Storage::disk("hr_system_folder");

        if ($storage->exists($this->filePath)) {
            $filePath = $storage->path($this->filePath);

            $excel->import(new ActualLogsImport, $filePath);
        }
    }
}
