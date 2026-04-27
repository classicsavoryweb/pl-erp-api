<?php

namespace App\Console\Commands;

use App\Models\Accounting\ApsysEntry;
use App\Models\Accounting\ApsysEntryMain;
use App\Models\Branch;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Entry;
use DB;
use Exception;
use Illuminate\Console\Command;
use Log;

class TransferUniformDeductions extends Command
{
    protected $signature = 'app:transfer-uniform-deductions';

    protected $description = 'Fetching uniform to employee deductions';

    public function handle()
    {
        $company = env('COMPANY_NAME');

        $uniforms = DB::connection("bridge_files")
        ->table("uniform_data_entry")
        ->where("company", $company)
        ->where("status", "Pending")
        ->get();

        foreach ($uniforms as $uniform) {
            try {
                DB::beginTransaction();
                $employee = Employee::where("employee_number", $uniform->employeeid)->first();

                if (!$employee) {
                    Log::info("EMPLOYEE NOT FOUND: ".$uniform->employeeid);
                    continue;
                }

                $first_initial = strtoupper(substr($employee->first_name, 0, 1));
                $middle_initial = strtoupper(substr($employee->middle_name ?? '', 0, 1));
                $last_initial = strtoupper(substr($employee->last_name, 0, 1));
                $employee_number = $employee->employee_number;

                $base_reference = $first_initial . $middle_initial . $last_initial . $employee_number;

                $deduction_count = EmployeeDeduction::where("employee_id", $employee->id)
                    ->whereNotNull("deduction_ref")
                    ->where("deduction_ref", "!=", '')
                    ->count();

                $deductionId = str_pad(8, 8, '0', STR_PAD_LEFT);

                $deduction_reference = $base_reference . $deductionId . "|" . ($deduction_count + 1);

                $branch = Branch::where("name", "Head Office")->first();

                $deduction_id = 8;

                $deduction = Deduction::where("id", $deduction_id)->first();

                $employeeDeduction = EmployeeDeduction::create([
                    "employee_id" => $employee->id,
                    "deduction_id" => $deduction->id,
                    "months" => $uniform->months,
                    "amount" => $uniform->amount,
                    "source_of_fund" => $branch->short_name,
                    "start_date" => $uniform->startdate,
                    "end_date" => $uniform->enddate,
                    "monthly_amortization" => $uniform->monthly_amortization,
                    "balance" => $uniform->amount,
                    "remarks" => $uniform->remarks,
                    "debit_id" => $deduction->debit_id,
                    "credit_id" => $deduction->credit_id,
                    "branch_id" => $branch->id,
                    "block_payment" => "Yes",
                    "approved_by" => null,
                    "approved_date" => null,
                    "approved_status" => "Yes",
                    "deduction_ref" => $deduction_reference,
                    "deduction_status" => null,
                    "created_by" => "Uniform System",
                    "date_created" => date('Y-m-d'),
                    "end_loan_reason" => null,
                    "end_loan_status" => "No",
                    "source" => "MANUAL"
                ]);

                $branchHO = Branch::where('short_name', 'HO')->first();
                $branchHOId = $branchHO->id;
                $branchHOName = $branchHO->name;
                $branchHONumber = $branchHO->branch_number;    

                $deductionEntry = Deduction::where('id', $deduction->id)->first();
                $debitId = $deductionEntry->debit_id;
                $creditId = $deductionEntry->credit_id;

                $entries = [];

                if ($employeeDeduction->amount > 0) {
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
                            'is_block_payment'    => 0,
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
                            'is_block_payment'    => 0,
                        ]
                    ]);
                }

                // $this->createEntries($entries);

                DB::connection("bridge_files")
                ->table("uniform_data_entry")
                ->where("id", $uniform->id)
                ->update([
                    "status" => "Completed"
                ]);

                DB::commit();
            } catch (Exception $e) {
                Log::info($e);
                DB::rollBack();
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

    private function createEntries($entries)
    {
        foreach ($entries as $entry) {

            Entry::create([
                'entry_id'                   => $entry['entry_id'],
                'document_no'                => $entry['document_no'],
                'branch_id'                  => $entry['branch_id'],
                'branch_number'              => $entry['branch_number'],
                'vendor_no'                  => $entry['ven_no'],
                'amount'                     => $entry['amount'],
                'account_no'                 => $entry['acc_no'],
                'description'                => $entry['description_per_acc'],
                'balance_type'               => $entry['balance_type'],
                'is_block_payment'           => $entry['is_block_payment'],
                'status'                     => 'Unapplied',
                'generated_at'               => now(),
                'generated_by'               => 2,
            ]);

            ApsysEntry::create([
                'ent_tran_no'       => $entry['apsys_entry_id'],
                'branch_id'         => $entry['branch_number'],
                'ent_post_date'     => now(),
                'ven_no'            => $entry['ven_no'],
                'ent_doc_no'        => $entry['document_no'],
                'ent_doc_date'      => now(),
                'ent_due_date'      => now(),
                'ent_header'        => '',
                'ent_crf_no'        => '',
                'ent_trans_amt'     => $entry['amount'],
                'ent_amt_due'       => $entry['amount'],
                'ent_module'        => 'Payroll',
                'acc_no'            => $entry['acc_no'],
                'ent_amt_per_acc'   => $entry['amount'],
                'ent_desc_per_acc'  => $entry['description_per_acc'],
                'ent_norm_bal'      => $entry['balance_type'],
                'ent_total'         => $entry['amount'],
                'ent_module_type'   => 'Single',
                'ent_app_amt'       => $entry['amount'],
                'ent_unapp_amt'     => $entry['amount'],
                'ent_vat_amt'       => 0,
                'ent_nonvat_amt'    => 0,
                'ent_na_amt'        => $entry['amount'],
                'ent_inp_tax_amt'   => 0,
                'ent_block_payment' => ($entry['is_block_payment'] == 1) ? 'Yes' : 'No',
                'ent_status'        => 'Unapplied',
                'ent_chk'           => 'No',
                'date_added'        => now(),
            ]);

            ApsysEntryMain::create([
                'ent_tran_no'       => $entry['apsys_entry_main_id'],
                'ven_no'            => $entry['ven_no'],
                'ven_name'          => $entry['ven_name'],
                'ent_doc_date'      => now(),
                'branch_id'         => $entry['branch_number'],
                'branch_name'       => $entry['branch_name'],
                'ent_header'        => '',
                'ent_trans_amt'     => $entry['amount'],
                'ent_post_date'     => now(),
                'ent_module_type'   => 'Single',
                'ent_status'        => 'Unapplied',
                'check_no'          => null,
                'ent_crf_no'        => '',
                'ent_doc_no'        => $entry['document_no'],
                'ent_module'        => 'Payroll',
                'ent_app_amt'       => $entry['amount'],
                'ent_unapp_amt'     => $entry['amount'],
                'ent_block_payment' => ($entry['is_block_payment'] == 1) ? 'Yes' : 'No',
                'ent_due_date'      => now(),
            ]);
        }
    }
}
