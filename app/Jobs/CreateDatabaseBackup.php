<?php

namespace App\Jobs;

use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str; 
use App\Support\NotifiesJobStatus;

class CreateDatabaseBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use NotifiesJobStatus;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle()
    {
        $filename = "database-backup-" . Carbon::now()->format('Y-m-d-H-i-s') . ".sql"; 

        $tempFilename = Str::uuid() . '.sql';
        $tempFilePath = storage_path('app/temp_backups') . DIRECTORY_SEPARATOR . $tempFilename;

        if (!file_exists(storage_path('app/temp_backups'))) {
            mkdir(storage_path('app/temp_backups'), 0755, true);
        }

        $mysqldumpPath = env("SQL_DUMP_PATH"); 
        
        $command = "$mysqldumpPath --user=" . env('DB_USERNAME') .
           " --password=" . env('DB_PASSWORD') .
           " --host=" . env('DB_HOST') .
           " --port=" . env('DB_PORT', 3306) .
           " " . env('DB_DATABASE') .
           " > " . $tempFilePath;
        
        $returnVar = NULL;
        $output = NULL;
        
        try {
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                throw new Exception("Database backup command failed.");
            }

            $storage = Storage::disk("hr_system_folder");
            $directory = "/Database Backups"; 

            if (!$storage->exists($directory)) {
                $storage->makeDirectory($directory);
            }

            $finalFilename = $filename; 
            $filePathOnDisk = $directory . '/' . $finalFilename;

            $storage->put($filePathOnDisk, file_get_contents($tempFilePath));

            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }

            $employee_name = 'System'; 
            if ($this->user && $this->user->employee) {
                $employee_name = $this->user->employee->full_name;
            }

            DatabaseBackup::create([
                'date' => now(),
                'file_name' => $finalFilename,
                'file_path' => $filePathOnDisk,
                'backed_up_by' => $employee_name,
            ]);

            $this->notifyJobFinished();
        } catch (Exception $e) {
            $this->notifyJobFailed();
            throw $e; 
        } finally {
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        }
    }
}
