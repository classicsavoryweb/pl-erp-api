<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\Accounting\ApsysEntry;
use App\Models\Accounting\ApsysEntryMain;
use App\Models\Accounting\Vendor;
use App\Models\Branch;
use App\Models\Entry;
use App\Models\PettyCashIssuance;
use App\Models\PettyCashLiquidation;
use App\Models\PettyCashReplenishment;
use App\Support\EntryBranchNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Exception;

class PettyCashAuditController extends Controller
{
    // "Pending" dito ay tumutukoy sa mga Posted na ni custodian pero pending pa i-audit
    private function buildAuditReplenishmentQuery(Request $request)
    {
        $statusMap = [
            'Pending'  => 'Posted',
            'Reviewed' => 'Reviewed',
            'Audited'  => 'Audited',
            'Replenished'  => 'Replenished',
        ];

        $query = PettyCashReplenishment::with(['custodian.user']);

        if ($request->filled('status')) {
            $query->where('status', $statusMap[$request->status] ?? 'Posted');
        } else {
            // walang piniling status filter, ipakita lahat ng nasa audit pipeline
            $query->whereIn('status', ['Posted', 'Reviewed', 'Audited', 'Replenished']);
        }

        if ($request->filled('custodian_id')) {
            $query->where('petty_cash_custodian_id', $request->custodian_id);
        }

        if ($request->filled('replenishment_id')) {
            $query->where('replenishment_id', 'like', '%' . $request->replenishment_id . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        if ($request->filled('branch_id')) {
            $branch = Branch::find($request->branch_id);
            if ($branch) {
                $query->where('branches_summary', 'like', '%' . $branch->name . '%');
            }
        }

        return $query;
    }

    public function getAllReplenishmentsForAudit(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $replenishments = $this->buildAuditReplenishmentQuery($request)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->sendResponse($replenishments);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    // detailed export - isang row per liquidation (kapareho ng audit view), hindi per replenishment
    public function exportAllReplenishmentsForAudit(Request $request)
    {
        try {
            $replenishments = $this->buildAuditReplenishmentQuery($request)
                ->with('snapshots')
                ->orderBy('replenishment_id', 'desc')
                ->get();

            $headers = [
                'Replenishment ID', 'Custodian', 'PCV Number', 'Liquidation ID', 'Transaction Date',
                'Requested By', 'Received By', 'Approved By', 'Cash Issuance Amount', 'Over/Under Amount',
                'Source', 'Liquidation Date', 'Ref Doc No.', 'Description', 'Liquidated Amount',
                'Expense Type', 'Snapshot Status', 'Current Status',
            ];

            $employeeName = fn($emp) => $emp ? $emp->last_name . ', ' . $emp->first_name : '—';

            // liquidated muna lahat sa taas, tapos unliquidated sa baba - hindi
            // pinagsasabay per replenishment dahil nakakalito pag paulit-ulit lumabas
            // ang parehong CI (Unliquidated noong isang replenishment, Liquidated na sa sunod)
            $liquidatedRows = collect();
            $unliquidatedRows = collect();

            foreach ($replenishments as $replenishment) {
                $custodianName = $replenishment->custodian->user->full_name ?? '—';

                // kapareho ng scope rule sa view: Liquidated-at-snapshot lang ang may liquidation detail
                $liquidatedSnapshots = $replenishment->snapshots
                    ->where('status_at_snapshot', 'Liquidated')
                    ->keyBy('petty_cash_issuance_id');

                $liquidations = PettyCashLiquidation::with(['expenseType', 'issuance.requestedBy', 'issuance.receivedBy', 'issuance.approvedBy'])
                    ->whereIn('petty_cash_issuance_id', $liquidatedSnapshots->keys())
                    ->orderBy('liquidation_id', 'asc')
                    ->get();

                foreach ($liquidations as $liquidation) {
                    $issuance = $liquidation->issuance;
                    $snapshot = $liquidatedSnapshots->get($liquidation->petty_cash_issuance_id);

                    $liquidatedRows->push([
                        $replenishment->replenishment_id,
                        $custodianName,
                        $snapshot->pcv_number ?? '—',
                        $liquidation->liquidation_id,
                        $issuance->date ?? '—',
                        $employeeName($issuance->requestedBy),
                        $employeeName($issuance->receivedBy),
                        $employeeName($issuance->approvedBy),
                        $issuance->amount,
                        $issuance->overunder_amount,
                        $issuance->source ?? '—',
                        $liquidation->date,
                        $liquidation->reference_document_number,
                        $liquidation->description,
                        $liquidation->amount,
                        $liquidation->expenseType->description ?? '—',
                        $snapshot->status_at_snapshot,
                        $issuance->status ?? '—',
                    ]);
                }

                // display-only reference, gaya ng sa view - mga Unliquidated pa noong snapshot
                $unliquidatedSnapshots = $replenishment->snapshots->where('status_at_snapshot', 'Unliquidated');

                foreach ($unliquidatedSnapshots as $snapshot) {
                    $issuance = PettyCashIssuance::with(['requestedBy', 'receivedBy', 'approvedBy'])
                        ->find($snapshot->petty_cash_issuance_id);

                    if (!$issuance) {
                        continue;
                    }

                    $unliquidatedRows->push([
                        $replenishment->replenishment_id,
                        $custodianName,
                        $snapshot->pcv_number ?? '—',
                        '—',
                        $issuance->date ?? '—',
                        $employeeName($issuance->requestedBy),
                        $employeeName($issuance->receivedBy),
                        $employeeName($issuance->approvedBy),
                        $issuance->amount,
                        '—',
                        $issuance->source ?? '—',
                        '—',
                        '—',
                        '—',
                        '—',
                        '—',
                        $snapshot->status_at_snapshot,
                        $issuance->status ?? $snapshot->status_at_snapshot,
                    ]);
                }
            }

            $rows = $liquidatedRows->concat($unliquidatedRows);

            $export = new \App\Exports\PettyCash\AuditLiquidationExport($rows, $headers);
            return \Maatwebsite\Excel\Facades\Excel::download($export, 'petty-cash-audit-liquidations.xls');
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function reviewReplenishment($id)
    {
        try {
            DB::beginTransaction();

            $replenishment = PettyCashReplenishment::lockForUpdate()->findOrFail($id);

            if ($replenishment->status !== 'Posted') {
                DB::rollBack();
                return $this->sendError(['message' => 'Only Posted replenishments can be marked as Reviewed.']);
            }

            $replenishment->update([
                'status'         => 'Reviewed',
                'reviewed_by_id' => auth()->id(),
                'reviewed_at'    => now(),
            ]);

            DB::commit();
            return $this->sendResponse(['message' => 'Replenishment marked as Reviewed.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function postAuditReplenishment($id)
    {
        try {
            DB::beginTransaction();

            $replenishment = PettyCashReplenishment::with('snapshots')->lockForUpdate()->findOrFail($id);

            if ($replenishment->status !== 'Reviewed') {
                DB::rollBack();
                return $this->sendError(['message' => 'Only Reviewed replenishments can be marked as Audited.']);
            }

            // i-validate muna ang expense_type_id bago mag-sulat ng kahit anong entry
            $liquidationGroups = $this->collectQualifyingLiquidations($replenishment);

            $missingExpenseType = [];
            foreach ($liquidationGroups as $group) {
                if (!$group['liquidation']->expense_type_id) {
                    $missingExpenseType[] = $group['liquidation']->liquidation_id;
                }
            }

            if ($missingExpenseType) {
                DB::rollBack();
                return $this->sendError([
                    'message' => 'The following liquidation(s) are missing an Expense Type and must be corrected before this replenishment can be Audited: ' . implode(', ', $missingExpenseType),
                ]);
            }

            $cshubSyncQueue = [];

            foreach ($replenishment->snapshots as $snapshot) {
                if ($snapshot->status_at_snapshot !== 'Liquidated') {
                    continue;
                }

                $issuance = PettyCashIssuance::where('id', $snapshot->petty_cash_issuance_id)
                    ->where('status', 'Liquidated')
                    ->first();

                if (!$issuance) {
                    continue;
                }

                $issuance->update(['status' => 'Fully Liquidated']);

                if ($issuance->cshub_trans_no) {
                    $cshubSyncQueue[] = $issuance;
                }
            }

            if ($this->isApsysAvailable()) {
                $this->createApsysEntriesForReplenishment($replenishment, $liquidationGroups);
            }

            $replenishment->update([
                'status'        => 'Audited',
                'audited_by_id' => auth()->id(),
                'audited_at'    => now(),
            ]);

            DB::commit();

            // best effort na lang toh, di na kailangan i-rollback yung audit post
            $cshubSyncErrors = [];
            foreach ($cshubSyncQueue as $issuance) {
                try {
                    $this->updateCSHubTransactionStatus($issuance->cshub_id, 'fully-liquidated', $issuance->requested_by_employee_number);
                } catch (Exception $e) {
                    $cshubSyncErrors[] = $issuance->pcv_number . ': ' . $e->getMessage();
                }
            }

            $message = 'Replenishment marked as Audited.';
            if ($cshubSyncErrors) {
                $message .= ' However, failed to sync status to CS Hub for: ' . implode('; ', $cshubSyncErrors);
            }

            return $this->sendResponse(['message' => $message]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    // same lang ginamit dito sa CashIssuanceController/ReplenishmentController
    private function updateCSHubTransactionStatus($cshubId, $status, $employeeNumber)
    {
        $baseUrl = env('CS_HUB_URL_LINK');
        $user = env('CS_HUB_ADMIN_EMAIL');
        $password = env('CS_HUB_ADMIN_PASSWORD');
        $company = env('COMPANY_NAME');

        $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post(rtrim($baseUrl, '/') . '/update-petty-cash-transaction-status', [
                'type'               => 'PETTY_CASH',
                'status'             => $status,
                'transaction_number' => $cshubId,
                'employee_number'    => $employeeNumber,
                'company'            => $company,
            ]);

        if (!$response->successful()) {
            $message = $response->json('data.message') ?: $response->body();
            throw new Exception($message);
        }
    }

    // isa-isa per liquidation, hindi per issuance o per replenishment
    private function collectQualifyingLiquidations(PettyCashReplenishment $replenishment): array
    {
        $groups = [];

        foreach ($replenishment->snapshots as $snapshot) {
            if ($snapshot->status_at_snapshot !== 'Liquidated') {
                continue;
            }

            $issuance = PettyCashIssuance::with('branch')->find($snapshot->petty_cash_issuance_id);
            if (!$issuance) {
                continue;
            }

            $liquidations = PettyCashLiquidation::with('expenseType')
                ->where('petty_cash_issuance_id', $issuance->id)
                ->where('amount', '>', 0)
                ->get();

            foreach ($liquidations as $liquidation) {
                $groups[] = ['liquidation' => $liquidation, 'issuance' => $issuance];
            }
        }

        return $groups;
    }

    private function isApsysAvailable(): bool
    {
        try {
            DB::connection('apsys')->getPdo();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveHoVendor(): ?Vendor
    {
        return Vendor::where('ven_name', 'HO')->first();
    }

    // mirror ng GenerateEntry.php's persistence pattern, pra d magalaw yung shared component ng entry
    private function createApsysEntriesForReplenishment(PettyCashReplenishment $replenishment, array $liquidationGroups): void
    {
        $vendor = $this->resolveHoVendor();

        foreach ($liquidationGroups as $group) {
            $liquidation = $group['liquidation'];
            $issuance = $group['issuance'];

            // idempotency guard - for uniqueness
            if (ApsysEntryMain::where('ent_crf_no', $liquidation->liquidation_id)->exists()) {
                continue;
            }

            $expenseType = $liquidation->expenseType;
            if (!$expenseType) {
                continue;
            }

            $branch = $issuance->branch;
            $branchNumber = EntryBranchNumber::normalizeForCurrentCompany($branch->branch_number ?? null);
            $amount = round((float) $liquidation->amount, 2);
            $description = $expenseType->description ?? '';
            $debitAcc = $expenseType->debit_id ?? null;
            $creditAcc = $expenseType->credit_id ?? null;
            $today = now()->toDateString();

            $entryMain = ApsysEntryMain::create([
                'ent_tran_no'       => '',
                'ven_no'            => $vendor->ven_no ?? null,
                'ven_name'          => $vendor->ven_name ?? null,
                'ent_doc_date'      => $today,
                'branch_id'         => $branchNumber,
                'branch_name'       => $branch->name ?? '',
                'ent_header'        => $replenishment->replenishment_id,
                'ent_trans_amt'     => $amount,
                'ent_post_date'     => $today,
                'ent_module_type'   => 'Single',
                'ent_status'        => 'Unapplied',
                'check_no'          => null,
                'ent_crf_no'        => $liquidation->liquidation_id,
                'ent_doc_no'        => $replenishment->replenishment_id,
                'ent_module'        => 'Payroll',
                'ent_app_amt'       => $amount,
                'ent_unapp_amt'     => 0,
                'ent_block_payment' => 'No',
                'ent_due_date'      => $today,
            ]);

            $tranNo = $entryMain->ent_tran_no;

            foreach ([
                ['acc_no' => $debitAcc, 'balance_type' => 'Debit'],
                ['acc_no' => $creditAcc, 'balance_type' => 'Credit'],
            ] as $line) {
                Entry::create([
                    'entry_id'         => $tranNo,
                    'pay_date'         => $today,
                    'document_no'      => $replenishment->replenishment_id,
                    'branch_id'        => $issuance->branch_id,
                    'branch_number'    => $branchNumber,
                    'vendor_no'        => $vendor->ven_no ?? null,
                    'amount'           => $amount,
                    'account_no'       => $line['acc_no'],
                    'description'      => $description,
                    'balance_type'     => $line['balance_type'],
                    'is_block_payment' => 0,
                    'status'           => 'Unapplied',
                    'generated_at'     => now(),
                    'generated_by'     => auth()->id(),
                ]);

                ApsysEntry::create([
                    'ent_tran_no'       => $tranNo,
                    'ent_post_date'     => $today,
                    'ven_no'            => $vendor->ven_no ?? null,
                    'ent_doc_date'      => $today,
                    'branch_id'         => $branchNumber,
                    'ent_doc_no'        => $replenishment->replenishment_id,
                    'ent_due_date'      => $today,
                    'ent_header'        => $replenishment->replenishment_id,
                    'ent_crf_no'        => $liquidation->liquidation_id,
                    'ent_trans_amt'     => $amount,
                    'ent_amt_due'       => $amount,
                    'ent_module'        => 'Payroll',
                    'acc_no'            => $line['acc_no'],
                    'ent_amt_per_acc'   => $amount,
                    'ent_desc_per_acc'  => $description,
                    'ent_norm_bal'      => $line['balance_type'],
                    'ent_total'         => $amount,
                    'ent_module_type'   => 'Single',
                    'ent_app_amt'       => $amount,
                    'ent_unapp_amt'     => 0,
                    'ent_vat_amt'       => 0,
                    'ent_nonvat_amt'    => 0,
                    'ent_na_amt'        => $amount,
                    'ent_inp_tax_amt'   => 0,
                    'ent_block_payment' => 'No',
                    'ent_status'        => 'Unapplied',
                    'ent_chk'           => 'No',
                    'date_added'        => $today,
                ]);
            }
        }
    }
}
