<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\OtherCompany\OtherEmployee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Throwable;

class SyncBranchDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-branch-details';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync branches from cs-erp.branches into savory_temp_db.branchdetails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting branch sync to savory_temp_db.branchdetails');
        $lastSynced = Cache::get('sync_branch_details.last_synced_at', now()->subDay()->startOfDay());
        $date = Carbon::parse($lastSynced)->format('Y-m-d H:i:s');

        $targetColumns = Schema::connection('savory_temp_db')->getColumnListing('branchdetails');
        $targetColumnKeys = array_flip($targetColumns);

        $keyColumn = null;
        if (isset($targetColumnKeys['branchID'])) {
            $keyColumn = 'branchID';
        } elseif (isset($targetColumnKeys['branch_id'])) {
            $keyColumn = 'branch_id';
        }

        if (!$keyColumn) {
            $this->error('branchdetails key column not found (branchID/branch_id).');
            return Command::FAILURE;
        }

        $batchSize = 200;

        Branch::orderBy('id')->chunkById($batchSize, function ($branches) use ($targetColumnKeys, $keyColumn) {
                $payload = [];
                $roleEmployeeNumbers = $branches
                    ->flatMap(function ($branch) {
                        return [
                            $branch->branch_head_employee_number,
                            $branch->group_head_employee_number,
                            $branch->kitchen_oic_employee_number,
                            $branch->mancom_employee_number,
                        ];
                    })
                    ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
                    ->map(fn ($value) => trim((string) $value))
                    ->unique()
                    ->values();

                $companyByEmployeeNumber = [];
                if ($roleEmployeeNumbers->isNotEmpty()) {
                    $employees = Employee::query()
                        ->select(['employee_number', 'branch_id', 'home_branch_id'])
                        ->whereIn('employee_number', $roleEmployeeNumbers)
                        ->get();

                    $otherEmployees = OtherEmployee::query()
                        ->select(['employee_number', 'branch_id', 'home_branch_id'])
                        ->whereIn('employee_number', $roleEmployeeNumbers)
                        ->get();

                    $branchIds = $employees->pluck('home_branch_id')
                        ->merge($employees->pluck('branch_id'))
                        ->merge($otherEmployees->pluck('home_branch_id'))
                        ->merge($otherEmployees->pluck('branch_id'))
                        ->filter()
                        ->unique()
                        ->values();

                    $branchCompanyById = $branchIds->isNotEmpty()
                        ? Branch::query()->whereIn('id', $branchIds)->pluck('company', 'id')
                        : collect();

                    foreach ($employees as $employee) {
                        $company = $branchCompanyById[$employee->home_branch_id]
                            ?? $branchCompanyById[$employee->branch_id]
                            ?? null;
                        if ($company) {
                            $companyByEmployeeNumber[$employee->employee_number] = $company;
                        }
                    }

                    foreach ($otherEmployees as $employee) {
                        if (isset($companyByEmployeeNumber[$employee->employee_number])) {
                            continue;
                        }
                        $company = $branchCompanyById[$employee->home_branch_id]
                            ?? $branchCompanyById[$employee->branch_id]
                            ?? null;
                        if ($company) {
                            $companyByEmployeeNumber[$employee->employee_number] = $company;
                        }
                    }
                }

                foreach ($branches as $branch) {
                    $companyShort = $this->companyShortName($branch->company);
                    $bhCompany = null;
                    if ($branch->branch_head_employee_number) {
                        $bhCompany = $companyByEmployeeNumber[trim((string) $branch->branch_head_employee_number)] ?? null;
                    }
                    $bhCompanyValue = $bhCompany ?? $branch->company;

                    $ghCompany = null;
                    if ($branch->group_head_employee_number) {
                        $ghCompany = $companyByEmployeeNumber[trim((string) $branch->group_head_employee_number)] ?? null;
                    }
                    $ghCompanyShort = $ghCompany ? $this->companyShortName($ghCompany) : $companyShort;

                    $kitchenCompany = null;
                    if ($branch->kitchen_oic_employee_number) {
                        $kitchenCompany = $companyByEmployeeNumber[trim((string) $branch->kitchen_oic_employee_number)] ?? null;
                    }
                    $kitchenCompanyShort = $kitchenCompany ? $this->companyShortName($kitchenCompany) : $companyShort;

                    $mancomCompany = null;
                    if ($branch->mancom_employee_number) {
                        $mancomCompany = $companyByEmployeeNumber[trim((string) $branch->mancom_employee_number)] ?? null;
                    }
                    $mancomCompanyShort = $mancomCompany ? $this->companyShortName($mancomCompany) : $companyShort;
                    $row = [
                        'branch_id' => $branch->id,
                        'branchID' => $branch->branch_number,
                        'branch_shrt_name' => $branch->short_name,
                        'branch_name' => $branch->name,
                        'bh_employeeid' => $branch->branch_head_employee_number,
                        'branch_head' => $branch->branch_head,
                        'bh_company' => $bhCompanyValue,
                        'company' => $companyShort,
                        'branch_address' => $branch->address,
                        'area' => $branch->location,
                        'region' => $branch->location,
                        'status' => is_string($branch->status) ? strtolower($branch->status) : $branch->status,
                        'branch_email' => $branch->branch_email,
                        'date_opened' => $branch->date_open,
                        'gh_employeeid' => $branch->group_head_employee_number,
                        'group_head' => $branch->group_head,
                        'gh_company' => $ghCompanyShort,
                        'kitchenID' => $branch->kitchen_oic_employee_number,
                        'kitchenName' => $branch->kitchen_oic,
                        'kitchenCompany' => $kitchenCompanyShort,
                        'mancomID' => $branch->mancom_employee_number,
                        'mancomName' => $branch->mancom,
                        'mancomCompany' => $mancomCompanyShort,
                    ];

                    $payload[] = array_intersect_key($row, $targetColumnKeys);
                }

                if (empty($payload)) {
                    return;
                }

                try {
                    foreach ($payload as $record) {
                        $table = DB::connection('savory_temp_db')->table('branchdetails');
                        $keyValue = $record[$keyColumn] ?? null;
                        if ($keyValue === null || $keyValue === '') {
                            continue;
                        }

                        $values = $record;
                        unset($values[$keyColumn]);

                        $exists = $table->where($keyColumn, $keyValue)->exists();
                        if ($exists) {
                            $table->where($keyColumn, $keyValue)->update($values);
                        } else {
                            $table->insert([$keyColumn => $keyValue] + $values);
                        }
                    }
                } catch (Throwable $th) {
                    Log::error('Failed to sync branches to savory_temp_db.branchdetails', [
                        'message' => $th->getMessage(),
                    ]);
                }
            });

        Cache::put('sync_branch_details.last_synced_at', now());
        $this->info('Branch sync completed.');

        return Command::SUCCESS;
    }

    private function companyShortName(?string $company): ?string
    {
        if ($company === null) {
            return null;
        }

        $normalized = strtoupper(trim($company));
        if ($normalized === '') {
            return $company;
        }

        if ($normalized === 'SFI' || $normalized === 'TTFC') {
            return $normalized;
        }

        if (str_contains($normalized, 'TONY TING FOOD CONCEPTS')) {
            return 'TTFC';
        }

         if (str_contains($normalized, 'SAVORY FAST FOOD')) {
            return 'SFI';
        }

        return $company;
    }
}
