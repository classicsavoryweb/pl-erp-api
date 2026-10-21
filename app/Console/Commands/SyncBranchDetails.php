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
        $this->info('Starting branch sync branchdetails to savory_temp_db');
        $syncFailed = false;
        $otherCompanyUnavailable = false;

        $targetColumns = Schema::connection('savory_temp_db')->getColumnListing('branchdetails');
        $targetColumnKeys = array_flip($targetColumns);

        $keyColumn = null;
        if (isset($targetColumnKeys['branch_id'])) {
            $keyColumn = 'branch_id';
        } elseif (isset($targetColumnKeys['branchID'])) {
            $keyColumn = 'branchID';
        }

        if (!$keyColumn) {
            $this->error('branchdetails key column not found (branchID/branch_id).');
            return Command::FAILURE;
        }

        $batchSize = 200;
        $currentCompany = $this->companyShortName(env('COMPANY_NAME'));

        if (!$currentCompany) {
            $this->error('COMPANY_NAME env is not set to SFI or TTFC');
            return Command::FAILURE;
        }

        $this->info("Syncing branches for company: {$currentCompany}");

        Branch::query()
        ->where(function ($query) use ($currentCompany) {
            $query->where('company', $currentCompany);

            if ($currentCompany === 'SFI') {
                $query->orWhere('company', 'LIKE', '%SAVORY FAST FOOD%');
            } elseif ($currentCompany === 'TTFC') {
                $query->orWhere('company', 'LIKE', '%TONY TING FOOD CONCEPTS%');
            }
        })
        ->orderBy('id')
        ->chunkById($batchSize, function ($branches) use ($targetColumnKeys, $keyColumn, &$syncFailed, &$otherCompanyUnavailable) {
                $payload = [];
                $roleEmployeeNumbers = $branches
                    ->flatMap(function ($branch) {
                        return [
                            $branch->branch_head_employee_number,
                            $branch->area_head_employee_number,
                            $branch->lab_head_employee_number,
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

                    try {
                        $otherEmployees = OtherEmployee::query()
                            ->select(['employee_number', 'branch_id', 'home_branch_id'])
                            ->whereIn('employee_number', $roleEmployeeNumbers)
                            ->get();
                    } catch (Throwable $th) {
                        $otherCompanyUnavailable = true;
                        Log::warning('other_company_db unavailable while syncing branch details; continuing with local employees only', [
                            'message' => $th->getMessage(),
                        ]);
                        $otherEmployees = collect();
                    }

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
                    if ($branch->area_head_employee_number) {
                        $ghCompany = $companyByEmployeeNumber[trim((string) $branch->area_head_employee_number)] ?? null;
                    }
                    $ghCompanyShort = $ghCompany ? $this->companyShortName($ghCompany) : $companyShort;

                    $kitchenCompany = null;
                    if ($branch->lab_head_employee_number) {
                        $kitchenCompany = $companyByEmployeeNumber[trim((string) $branch->lab_head_employee_number)] ?? null;
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
                        'gh_employeeid' => $branch->area_head_employee_number,
                        'area_head' => $branch->area_head,
                        'gh_company' => $ghCompanyShort,
                        'kitchenID' => $branch->lab_head_employee_number,
                        'kitchenName' => $branch->lab_head,
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
                    $syncFailed = true;
                    Log::error('Failed to sync branches to savory_temp_db.branchdetails', [
                        'message' => $th->getMessage(),
                    ]);
                }
            });

        if ($syncFailed) {
            $this->error('Branch sync finished with errors. Check logs for details.');
            return Command::FAILURE;
        }

        Cache::put('sync_branch_details.last_synced_at', now());
        if ($otherCompanyUnavailable) {
            $this->warn('Branch sync completed, but other_company_db lookup was skipped.');
        }
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
