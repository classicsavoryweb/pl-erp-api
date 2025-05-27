<?php

namespace App\Jobs;

use App\Imports\TardinessImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;

class ProcessTardinessImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tardinessFilePath;

    public function __construct(string $tardinessFilePath)
    {
        $this->tardinessFilePath = $tardinessFilePath;
    }

    public function handle(Excel $excel): void
    {
        $storage = Storage::disk("hr_system_folder");

        if ($storage->exists($this->tardinessFilePath)) {
            $filePath = $storage->path($this->tardinessFilePath);

            $excel->import(new TardinessImport, $filePath);
        }
    }
}
