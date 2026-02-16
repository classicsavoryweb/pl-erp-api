<?php

namespace App\Console\Commands;

use App\Models\Branch;
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

        Branch::where('updated_at', '>=', $date)
            ->orWhere('created_at', '>=', $date)
            ->orderBy('id')
            ->chunkById($batchSize, function ($branches) use ($targetColumnKeys, $keyColumn) {
                $payload = [];

                foreach ($branches as $branch) {
                    $companyShort = $this->companyShortName($branch->company);
                    $row = [
                        'branch_id' => $branch->id,
                        'branchID' => $branch->branch_number,
                        'branch_shrt_name' => $branch->short_name,
                        'branch_name' => $branch->name,
                        'bh_employeeid' => $branch->branch_head_employee_number,
                        'branch_head' => $branch->branch_head,
                        'bh_company' => $branch->company,
                        'company' => $companyShort,
                        'branch_address' => $branch->address,
                        'area' => $branch->location,
                        'region' => $branch->location,
                        'status' => is_string($branch->status) ? strtolower($branch->status) : $branch->status,
                        'branch_email' => $branch->branch_email,
                        'date_opened' => $branch->date_open,
                        'gh_employeeid' => $branch->group_head_employee_number,
                        'group_head' => $branch->group_head,
                        'gh_company' => $companyShort,
                        'kitchenID' => $branch->kitchen_oic_employee_number,
                        'kitchenName' => $branch->kitchen_oic,
                        'kitchenCompany' => $companyShort,
                        'mancomID' => $branch->mancom_employee_number,
                        'mancomName' => $branch->mancom,
                        'mancomCompany' => $companyShort,
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

        if (str_contains($normalized, 'SAVORY FASTFOOD')) {
            return 'SFI';
        }

        return $company;
    }
}
