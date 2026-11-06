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
use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\BackupDestination\BackupDestination;

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
        $disk = config('backup.backup.destination.disks')[0];
        $exitCode = Artisan::call('backup:run', [
            '--only-db'   => true,
            '--only-to-disk' => $disk,
            '--disable-notifications' => true,
        ]);

        if ($exitCode !== 0) {
            Log::error('Database backup failed', ['output' => Artisan::output()]);
            throw new Exception('Database backup failed.');
        }

        $backup = BackupDestination::create($disk, config('backup.backup.name'))->newestBackup();

        if (! $backup) {
            throw new Exception('Backup ran but no backup file was found.');
        }

        DatabaseBackup::create([
            'date'        => now(),
            'file_name'   => basename($backup->path()),
            'file_path'   => $backup->path(),
            'backed_up_by'=> $this->user->full_name,
        ]);
    }
}
