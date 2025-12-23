<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Throwable;

class SyncEmployeeDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-employee-details';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync new employees from cs-erp.employees into savory_temp_db.employeedetails (append only)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting employee append to savory_temp_db.employeedetails');
        $date = Carbon::parse(now())->subDays(1)->startOfDay()->format('Y-m-d H:i:s');
        $targetColumns = Schema::connection('savory_temp_db')->getColumnListing('employeedetails');
        $targetColumnKeys = array_flip($targetColumns);

        $existingIds = DB::connection('savory_temp_db')
            ->table('employeedetails')
            ->pluck('EmployeeID')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingSet = array_fill_keys($existingIds, true);
        $batchSize = 200;

        $positionNames = DB::table('positions')
            ->pluck('description', 'id')
            ->toArray();

        $branchNames = DB::table('branches')
            ->pluck('name', 'id')
            ->toArray();

        $employmentStatuses = DB::table('employee_status')
            ->pluck('description', 'id')
            ->toArray();

        DB::table('employees')
            ->where('created_at', '>=', $date)
            ->orWhere('updated_at', '>=', $date)
            ->orderBy('id')
            ->chunkById($batchSize, function ($employees) use ($targetColumnKeys, &$existingSet, $positionNames, $branchNames, $employmentStatuses) {
                $payload = [];

                foreach ($employees as $employee) {
                    $employeeId = $employee->employee_number;

                    if (isset($existingSet[$employeeId])) {
                        continue; 
                    }

                    $birth = $employee->birthday ? Carbon::parse($employee->birthday) : null;
                    $hire  = $employee->date_hired ? Carbon::parse($employee->date_hired) : null;

                    $age = $birth ? $birth->age : null;
                    $tenure = null;
                    if ($hire) {
                        $years  = $hire->diffInYears();
                        $months = $hire->copy()->addYears($years)->diffInMonths();
                        $tenure = "{$years} years {$months} months";
                    }

                    $row = [
                        'EmployeeID' => $employeeId,
                        'BiometricID' => $employee->biometric_number,
                        'Company' => env('COMPANY_NAME', 'SFI'),
                        'employeeType' => $employee->type,
                        'LastName' => $employee->last_name,
                        'FirstName' => $employee->first_name,
                        'MiddleName' => $employee->middle_name,
                        'Gender' => $employee->gender,
                        'PositionID' => $employee->position_id,
                        'Position' => $employee->current_position->description ?? null,
                        'BranchID' => $employee->current_branch->branch_number,
                        'CurrentBranch' => $employee->current_branch->branch_name ?? null,
                        'DateHired' => $employee->date_hired,
                        'Birthday' => $employee->birthday,
                        'ContactNo' => $employee->contact_number,
                        'DateSeparation' => $employee->separation_date,
                        'SeparationReason' => $employee->separation_reason,
                        'EmailAddress' => $employee->email,
                        'EmploymentStatus' => $employmentStatuses[$employee->employee_status_id] ?? null,
                        'Status' => $employee->status,
                        'HomeBranchID' => $employee->home_branch_id,
                        'HomeBranchName' => $branchNames[$employee->home_branch_id] ?? null,
                        'age' => $age,
                        'tenure' => $tenure,
                    ];

                    $payload[] = array_intersect_key($row, $targetColumnKeys);
                    $existingSet[$employeeId] = true;
                }

                if (empty($payload)) {
                    return;
                }

                try {
                    DB::connection('savory_temp_db')
                        ->table('employeedetails')
                        ->insert($payload);
                } catch (Throwable $th) {
                    Log::error('Failed to append employees to savory_temp_db', [
                        'message' => $th->getMessage(),
                    ]);
                }
            });

        $this->info('Employee append completed.');

        return Command::SUCCESS;
    }
}
