<?php

namespace App\Console\Commands;

use App\Models\Accounting\ApsysEntry;
use App\Models\Accounting\ApsysEntryMain;
use App\Models\Branch;
use App\Models\Deduction;
use App\Models\EmployeeDeduction;
use App\Models\Entry;
use App\Models\RequestAndIssuance;
use App\Models\Term;
use App\Models\User;
use App\Support\EntryBranchNumber;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateDeductionFromUniformATD extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:deduction-from-uniform-atd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'fetching all uniform deductions from atd module in cshub';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $requests = RequestAndIssuance::with(["employee", "items"])
        ->where("status", "Posted")
        ->where("issuance_type", "Authority To Deduct")
        ->where("atd_status", "ATD Acknowledged")
        ->whereNotNull("atd_transaction_number")
        ->get();

        $entries = [];

        foreach ($requests as $request) {
            $employee = $request->employee;

            if (!$employee) {
                continue;
            }

            $transactionLink = env('CS_HUB_URL_LINK')."/generate-atd/".$request->atd_transaction_number;
            $user = env('CS_HUB_ADMIN_EMAIL');
            $password = env('CS_HUB_ADMIN_PASSWORD');
            $company = env('COMPANY_NAME');

            $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                "Accept" => "application/json",
                "Content-Type" => "application/json"
            ])->timeout(60)
            ->post($transactionLink,[
                "company" => $company,
                "employee_numbers" => [$request->employee_id]
            ]);

            if (!$response->successful()) {
                continue;
            }

            $deduction = Deduction::where("description", "Uniform")->first();

            $first_initial = strtoupper(substr($employee->first_name, 0, 1));
            $middle_initial = strtoupper(substr($employee->middle_name ?? '', 0, 1));
            $last_initial = strtoupper(substr($employee->last_name, 0, 1));
            $employee_number = $employee->employee_number;

            $base_reference = $first_initial . $middle_initial . $last_initial . $employee_number;

            $deduction_count = EmployeeDeduction::where("employee_id", $employee->id)
                ->whereNotNull("deduction_ref")
                ->where("deduction_ref", "!=", '')
                ->count();
            
            $deductionId = str_pad($deduction->id, 8, '0', STR_PAD_LEFT);
            $deduction_reference = $base_reference . $deductionId . "|" . ($deduction_count + 1);

            $dateStart = now()->format("Y-m-d");

            $term = Term::where("amount_from", "<=", $request->total_amount)
            ->where("amount_to", ">=", $request->total_amount)
            ->first();

            $terms = $term ? $term->month : 0;
            $dateEnd = Carbon::parse($dateStart)->addMonths($terms)->format("Y-m-d");
            $monthlyAmortization = 0;
            if ($terms == 0) {
                $monthlyAmortization = $request->total_amount;
            } else {
                $monthlyAmortization = $request->total_amount / $terms;
            }

            $remarks = $request->atd_transaction_number." | ".Carbon::parse($request->date_posted)->format("Y-m-d")." | Uniform | ";

            foreach ($request->items as $idx => $item) {
                if ($idx === 0) {
                    $remarks = $remarks . $item->requested_quantity ." ". $item->description;
                } else {
                    $remarks = $remarks . " , " . $item->requested_quantity ." ". $item->description;
                }
            }

            $atdBranch = Branch::where("name", $request->branch)->first();
            if (!$atdBranch) {
                $atdBranch = Branch::where("id", $employee->branch_id)->first();
            }

            $employeeDeduction = EmployeeDeduction::updateOrCreate([
                "employee_id" => $employee->id,
                "deduction_id" => $deduction->id,
                "balance" => $request->total_amount,
                "transaction_number" => $request->request_number,
            ],[
                "months" => $terms,
                "amount" => $request->total_amount,
                "source_of_fund" => "HO",
                "start_date" => $dateStart,
                "end_date" => $dateEnd,
                "monthly_amortization" => $monthlyAmortization,
                "remarks" => $remarks,
                "debit_id" => $deduction->debit_id,
                "credit_id" => $deduction->credit_id,
                "branch_id" => $atdBranch->id,
                "block_payment" => "Yes",
                "approved_status" => "Yes",
                "deduction_ref" => $deduction_reference,
                "created_by" => "Uniform System",
                "date_created" => $dateStart,
                "end_loan_status" => "No",
                "source" => "CSHUB"
            ]);

            $request->update([
                "atd_status" => "ATD Generated"
            ]);

            $branchHO = Branch::where('short_name', 'HO')
            ->first();
            $branchHOId = $branchHO->id;
            $branchHOName = $branchHO->name;
            $branchHONumber = $branchHO->branch_number;    

            $deductionEntry = Deduction::where('id', $employeeDeduction->deduction_id)->first();
            $debitId = $deductionEntry->debit_id;
            $creditId = $deductionEntry->credit_id;

            if ($employeeDeduction->wasRecentlyCreated && $employeeDeduction->amount > 0) {
                $entryId = $this->generateEntryId($counterEntry);
                $apsysEntryId = $this->generateApsysEntryId($counterApsysEntry);
                $apsysEntryMainId = $this->generateApsysEntryMainId($counterApsysEntryMain);
                $entries = array_merge($entries, [
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $employee->employee_number . '|' . 'Head Office',
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $employee->employee_number,
                        'ven_name'            => $employee->full_name,
                        'amount'              => $employeeDeduction->amount,
                        'acc_no'              => $debitId,
                        'description_per_acc' => $employeeDeduction->deduction->description,
                        'balance_type'        => 'Debit',
                        'is_block_payment'    => $deductionEntry->is_block_payment,
                    ],
                    [
                        'entry_id'            => $entryId,
                        'apsys_entry_id'      => $apsysEntryId,
                        'apsys_entry_main_id' => $apsysEntryMainId,
                        'document_no'         => $employee->employee_number . '|' . 'Head Office',
                        'branch_id'           => $branchHOId,
                        'branch_number'       => $branchHONumber,
                        'branch_name'         => $branchHOName,
                        'ven_no'              => $employee->employee_number,
                        'ven_name'            => $employee->full_name,
                        'amount'              => $employeeDeduction->amount,
                        'acc_no'              => $creditId,
                        'description_per_acc' => $employeeDeduction->deduction->description,
                        'balance_type'        => 'Credit',
                        'is_block_payment'    => $deductionEntry->is_block_payment,
                    ]
                ]);
            }
        }

        $adminUser = User::where("email", "admin@classicsavory.com")->first();
        $this->createEntries($entries, $adminUser);
    }

    private function createEntries($entries, $user)
    {
        if (empty($entries)) {
            return;
        }

        $entries = EntryBranchNumber::normalizeEntriesForCurrentCompany($entries);

        $groupedEntries = collect($entries)->groupBy('apsys_entry_main_id');
        foreach ($groupedEntries as $mainId => $groupedEntry) {
            $entry = $groupedEntry->first();

            $entryMain = ApsysEntryMain::create([
                'ent_tran_no'       => $mainId,
                'ven_no'            => $entry['ven_no'],
                'ven_name'          => $entry['ven_name'],
                'ent_doc_date'      => now(),
                'branch_id'         => $entry['branch_number'],
                'branch_name'       => $entry['branch_name'],
                'ent_header'        => $entry['document_no'],
                'ent_trans_amt'     => $entry['amount'],
                'ent_post_date'     => now(),
                'ent_module_type'   => 'Single',
                'ent_status'        => 'Unapplied',
                'check_no'          => null,
                'ent_crf_no'        => '',
                'ent_doc_no'        => $entry['document_no'],
                'ent_module'        => 'Payroll',
                'ent_app_amt'       => $entry['amount'],
                'ent_unapp_amt'     => 0,
                'ent_block_payment' => ($entry['is_block_payment'] == 1) ? 'Yes' : 'No',
                'ent_due_date'      => now(),
            ]);

            foreach ($groupedEntry as $ent) {
                Entry::create([
                    'entry_id'                   => $entryMain->ent_tran_no,
                    'document_no'                => $ent['document_no'],
                    'branch_id'                  => $ent['branch_id'],
                    'branch_number'              => $ent['branch_number'],
                    'vendor_no'                  => $ent['ven_no'],
                    'amount'                     => $ent['amount'],
                    'account_no'                 => $ent['acc_no'],
                    'description'                => $ent['description_per_acc'],
                    'balance_type'               => $ent['balance_type'],
                    'is_block_payment'           => $ent['is_block_payment'],
                    'status'                     => 'Unapplied',
                    'generated_at'               => now(),
                    'generated_by'               => $user->id,
                ]);

                ApsysEntry::create([
                    'ent_tran_no'       => $entryMain->ent_tran_no,
                    'branch_id'         => $ent['branch_number'],
                    'ent_post_date'     => now(),
                    'ven_no'            => $ent['ven_no'],
                    'ent_doc_no'        => $ent['document_no'],
                    'ent_doc_date'      => now(),
                    'ent_due_date'      => now(),
                    'ent_header'        => '',
                    'ent_crf_no'        => '',
                    'ent_trans_amt'     => $ent['amount'],
                    'ent_amt_due'       => $ent['amount'],
                    'ent_module'        => 'Payroll',
                    'acc_no'            => $ent['acc_no'],
                    'ent_amt_per_acc'   => $ent['amount'],
                    'ent_desc_per_acc'  => $ent['description_per_acc'],
                    'ent_norm_bal'      => $ent['balance_type'],
                    'ent_total'         => $ent['amount'],
                    'ent_module_type'   => 'Single',
                    'ent_app_amt'       => $ent['amount'],
                    'ent_unapp_amt'     => $ent['amount'],
                    'ent_vat_amt'       => 0,
                    'ent_nonvat_amt'    => 0,
                    'ent_na_amt'        => $ent['amount'],
                    'ent_inp_tax_amt'   => 0,
                    'ent_block_payment' => ($ent['is_block_payment'] == 1) ? 'Yes' : 'No',
                    'ent_status'        => 'Unapplied',
                    'ent_chk'           => 'No',
                    'date_added'        => now(),
                ]);
            }
        }
    }

    private function generateEntryId(&$counterEntry)
    {
        if ($counterEntry === null) {
            $lastEntry = Entry::orderBy('entry_id', 'desc')->first();

            if ($lastEntry) {
                $lastEntryIdNumber = (int) substr($lastEntry->entry_id, 3);
                $counterEntry = $lastEntryIdNumber + 1;
            } else {
                $counterEntry = 1; 
            }
        }

        return 'PSD' . str_pad($counterEntry++, 8, '0', STR_PAD_LEFT)."-ERP";
    }

    private function generateApsysEntryId(&$counterApsysEntry)
    {
        if ($counterApsysEntry === null) {
            $lastEntry = ApsysEntry::orderBy('ent_tran_no', 'desc')->first();

            if ($lastEntry) {
                $lastEntryIdNumber = (int) substr($lastEntry->ent_tran_no, 3);
                $counterApsysEntry = $lastEntryIdNumber + 1;
            } else {
                $counterApsysEntry = 1; 
            }
        }

        return 'PSD' . str_pad($counterApsysEntry++, 8, '0', STR_PAD_LEFT)."-ERP";
    }

    private function generateApsysEntryMainId(&$counterApsysEntryMain)
    {
        if ($counterApsysEntryMain === null) {
            $lastEntry = ApsysEntryMain::orderBy('ent_tran_no', 'desc')->first();

            if ($lastEntry) {
                $lastEntryIdNumber = (int) substr($lastEntry->ent_tran_no, 3);
                $counterApsysEntryMain = $lastEntryIdNumber + 1;
            } else {
                $counterApsysEntryMain = 1; 
            }
        }

        return 'PSD' . str_pad($counterApsysEntryMain++, 8, '0', STR_PAD_LEFT)."-ERP";
    }
}
