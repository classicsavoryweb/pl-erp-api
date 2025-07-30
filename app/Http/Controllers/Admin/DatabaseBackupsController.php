<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\DatabaseBackup;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Exception;

use App\Jobs\CreateDatabaseBackup;
use Spatie\Backup\BackupDestination\BackupDestinationFactory;
use Spatie\Backup\Helpers\BackupDestination;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Jobs\CreateDatabaseBackupJob;

class DatabaseBackupsController extends Controller
{
    public function getAllDatabaseBackups (Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $database_backups = DatabaseBackup::query()->orderBy("date", "DESC")->paginate($perPage);

            return $this->sendResponse($database_backups);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createDatabaseBackup(Request $request)
    {
        try {
            $user = Auth::user();

            CreateDatabaseBackup::dispatch($user);

            return $this->sendResponse(['message' => 'Database Backup creation started.']);
        } catch (Exception $e) {
            return $this->sendError(['message' => 'Backup creation failed', 'error' => $e->getMessage()]);
        }
    }

    public function downloadSqlFile($filename) {
        try {
            $storage = Storage::disk("hr_system_folder");
            $directory = "/Database Backups"; 
            $filePathOnDisk = $directory . '/' . $filename;
            
            if (!$storage->exists($filePathOnDisk)) {
                return $this->sendError(["message" => "File not found"]);
            }
            
            $fileContents = $storage->get($filePathOnDisk);
            
            return response($fileContents)
                ->header('Content-Type', 'application/sql')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
