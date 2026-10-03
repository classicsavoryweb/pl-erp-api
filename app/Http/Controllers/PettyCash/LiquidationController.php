<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\ExpenseType;
use App\Models\PettyCashCustodian;
use App\Models\PettyCashIssuance;
use App\Models\PettyCashLiquidation;
use App\Models\PettyCashLiquidationAttachment;
use App\Models\PettyCashLiquidationAuditLog;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashReplenishmentSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;

class LiquidationController extends Controller
{
    // naka-freeze na yung snapshot pag may replenishment today, bawal muna mag-liquidate hanggang bukas
    private function hasBlockingReplenishmentToday($custodian)
    {
        return PettyCashReplenishment::where('petty_cash_custodian_id', $custodian->id)
            ->whereDate('date', now()->toDateString())
            ->whereIn('status', ['Pending', 'Posted'])
            ->exists();
    }

    private function saveAttachments(PettyCashLiquidation $liquidation, Request $request): void
    {
        if (!$request->hasFile('attachments')) return;

        $storage = Storage::disk('hr_system_folder');
        $directory = '/Liquidation Attachments/' . $liquidation->liquidation_id;

        if (!$storage->exists($directory)) {
            $storage->makeDirectory($directory);
        }

        foreach ($request->file('attachments') as $index => $file) {
            $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $directory . '/' . $fileName;
            $storage->put($filePath, file_get_contents($file));

            PettyCashLiquidationAttachment::create([
                'petty_cash_liquidation_id' => $liquidation->id,
                'file_path'                 => $filePath,
                'file_name'                 => $request->input("attachment_names.{$index}", $file->getClientOriginalName()),
            ]);
        }
    }

    public function getLiquidations($issuanceId)
    {
        try {
            $issuance = PettyCashIssuance::findOrFail($issuanceId);

            $liquidations = PettyCashLiquidation::with(['expenseType', 'createdBy', 'attachments', 'latestAuditLog.performedBy'])
                ->where('petty_cash_issuance_id', $issuanceId)
                ->orderBy('created_at')
                ->get();

            $count = PettyCashLiquidation::withTrashed()
                ->where('petty_cash_issuance_id', $issuanceId)
                ->count();

            $nextLiquidationId = 'LIQ' . ($count + 1) . '-' . $issuance->pcv_number;

            return $this->sendResponse([
                'liquidations'        => $liquidations,
                'next_liquidation_id' => $nextLiquidationId,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    // flattened na listahan ng lahat ng liquidations sa lahat ng issuance na kasama
    // sa isang replenishment - gamit ng Audit Review screen kaya't hindi na kailangang
    // pumasok pa isa-isa sa bawat cash issuance
    public function getLiquidationsForReplenishment($replenishmentId)
    {
        try {
            $replenishment = PettyCashReplenishment::with('snapshots')->findOrFail($replenishmentId);

            // scope lang sa mga issuance na 'Liquidated' na noong kinuha ang snapshot -
            // kung may na-liquidate mamaya na 'Unliquidated' pa noon, susunod na
            // replenishment cycle ang masasakop nito, hindi ito
            $snapshotsByIssuance = $replenishment->snapshots
                ->where('status_at_snapshot', 'Liquidated')
                ->keyBy('petty_cash_issuance_id');

            $liquidations = PettyCashLiquidation::with([
                    'expenseType',
                    'createdBy',
                    'attachments',
                    'issuance.requestedBy',
                    'issuance.receivedBy',
                    'issuance.approvedBy',
                    'latestAuditLog.performedBy',
                ])
                ->whereIn('petty_cash_issuance_id', $snapshotsByIssuance->keys())
                ->orderBy('petty_cash_issuance_id')
                ->orderBy('created_at')
                ->get()
                ->map(function ($liquidation) use ($snapshotsByIssuance) {
                    $snapshot = $snapshotsByIssuance->get($liquidation->petty_cash_issuance_id);
                    $liquidation->pcv_number = $snapshot->pcv_number ?? null;
                    $liquidation->cshub_trans_no_snapshot = $snapshot->cshub_trans_no ?? null;
                    return $liquidation;
                });

            // display-only reference: mga issuance na 'Unliquidated' pa noong snapshot -
            // wala silang action (view/edit) dahil hindi sakop ng replenishment na 'to,
            // pero ipapakita gamit ang live status ng CI para alam kung ano na kalagayan
            $unliquidatedSnapshots = $replenishment->snapshots
                ->where('status_at_snapshot', 'Unliquidated')
                ->values();

            $unliquidatedIssuancesById = PettyCashIssuance::with(['requestedBy', 'receivedBy', 'approvedBy'])
                ->whereIn('id', $unliquidatedSnapshots->pluck('petty_cash_issuance_id'))
                ->get()
                ->keyBy('id');

            $unliquidatedIssuances = $unliquidatedSnapshots->map(function ($snapshot) use ($unliquidatedIssuancesById) {
                $issuance = $unliquidatedIssuancesById->get($snapshot->petty_cash_issuance_id);
                return [
                    'petty_cash_issuance_id' => $snapshot->petty_cash_issuance_id,
                    'pcv_number'             => $snapshot->pcv_number,
                    'cshub_trans_no'         => $snapshot->cshub_trans_no,
                    'issuance'               => $issuance,
                    'current_status'        => $issuance->status ?? $snapshot->status_at_snapshot,
                ];
            })->values();

            return $this->sendResponse([
                'liquidations'           => $liquidations,
                'unliquidated_issuances' => $unliquidatedIssuances,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getLiquidationAttachment($id)
    {
        try {
            $attachment = PettyCashLiquidationAttachment::findOrFail($id);

            // cshub attachments, url lang naka-store, wala talaga dito yung file
            if ($attachment->source === 'CSHUB') {
                $response = Http::timeout(30)->get($attachment->file_path);

                if (!$response->successful()) {
                    return $this->sendError(['message' => 'File not found on CS Hub.']);
                }

                $mimeType = $response->header('Content-Type') ?: 'application/octet-stream';
                $fileData = 'data:' . $mimeType . ';base64,' . base64_encode($response->body());

                return $this->sendResponse([
                    'id'        => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'mime_type' => $mimeType,
                    'file_data' => $fileData,
                ]);
            }

            $storage = Storage::disk('hr_system_folder');

            if (!$storage->exists($attachment->file_path)) {
                return $this->sendError(['message' => 'File not found.']);
            }

            $mimeType = $storage->mimeType($attachment->file_path);
            $fileData = 'data:' . $mimeType . ';base64,' . base64_encode($storage->get($attachment->file_path));

            return $this->sendResponse([
                'id'        => $attachment->id,
                'file_name' => $attachment->file_name,
                'mime_type' => $mimeType,
                'file_data' => $fileData,
            ]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function createLiquidation(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'petty_cash_issuance_id'   => 'required|exists:petty_cash_issuances,id',
                'liquidation_type'          => 'required|string',
                'reference_document_number' => 'required|string|max:50',
                'reference_document_date'   => 'required|date|before_or_equal:today',
                'description'               => 'required|string|max:250',
                'supplier_vendor'           => 'required|string|max:150',
                'amount'                    => 'required|numeric|min:0',
                'expense_type_id'           => 'required|exists:expense_types,id',
                'attachments'               => 'required|array|min:1',
                'attachments.*'             => 'required|file|mimetypes:image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/svg+xml,application/pdf',
            ]);

            $issuance = PettyCashIssuance::findOrFail($request->petty_cash_issuance_id);

            $count = PettyCashLiquidation::withTrashed()
                ->where('petty_cash_issuance_id', $issuance->id)
                ->count();

            $liquidationId = 'LIQ' . ($count + 1) . '-' . $issuance->pcv_number;

            $liquidation = PettyCashLiquidation::create([
                'liquidation_id'               => $liquidationId,
                'petty_cash_issuance_id'       => $issuance->id,
                'date'                         => now()->toDateString(),
                'liquidation_type'             => $request->liquidation_type,
                'requested_by_employee_number' => $issuance->requested_by_employee_number,
                'reference_document_number'    => $request->reference_document_number,
                'reference_document_date'      => $request->reference_document_date,
                'description'                  => $request->description,
                'supplier_vendor'              => $request->supplier_vendor,
                'amount'                       => $request->amount,
                'expense_type_id'              => $request->expense_type_id,
                'created_by_id'                => auth()->id(),
            ]);

            $this->saveAttachments($liquidation, $request);

            DB::commit();
            return $this->sendResponse(['message' => 'Liquidation created successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function editLiquidation(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'liquidation_type'          => 'required|string',
                'reference_document_number' => 'required|string|max:50',
                'reference_document_date'   => 'required|date|before_or_equal:today',
                'description'               => 'required|string|max:250',
                'supplier_vendor'           => 'required|string|max:150',
                'amount'                    => 'required|numeric|min:0',
                'expense_type_id'           => 'required|exists:expense_types,id',
                'attachments.*'             => 'file|mimetypes:image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/svg+xml,application/pdf',
            ]);

            $liquidation = PettyCashLiquidation::findOrFail($id);
            $liquidation->update([
                'liquidation_type'          => $request->liquidation_type,
                'reference_document_number' => $request->reference_document_number,
                'reference_document_date'   => $request->reference_document_date,
                'description'               => $request->description,
                'supplier_vendor'           => $request->supplier_vendor,
                'amount'                    => $request->amount,
                'expense_type_id'           => $request->expense_type_id,
                'updated_by_id'             => auth()->id(),
            ]);

            // alisin yung mga tinanggal na attachment
            $removedIds = [];
            if ($request->filled('removed_attachment_ids')) {
                $storage = Storage::disk('hr_system_folder');
                $removedIds = is_array($request->removed_attachment_ids)
                    ? $request->removed_attachment_ids
                    : explode(',', $request->removed_attachment_ids);

                foreach ($removedIds as $attId) {
                    $att = PettyCashLiquidationAttachment::find($attId);
                    if ($att) {
                        if ($storage->exists($att->file_path)) $storage->delete($att->file_path);
                        $att->delete();
                    }
                }
            }

            $remainingCount = PettyCashLiquidationAttachment::where('petty_cash_liquidation_id', $liquidation->id)->count();
            $incomingCount  = $request->hasFile('attachments') ? count($request->file('attachments')) : 0;

            if ($remainingCount + $incomingCount < 1) {
                DB::rollBack();
                return $this->sendError(['message' => 'At least one attachment is required.']);
            }

            $this->saveAttachments($liquidation, $request);

            DB::commit();
            return $this->sendResponse(['message' => 'Liquidation updated successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    // sinisiguro na kasama nga sa snapshot ng replenishment na 'to yung issuance,
    // at nasa tamang status pa ang replenishment (Posted = review, Reviewed = post-audit)
    private function resolveReplenishmentForAuditEdit($replenishmentId, $issuanceId): PettyCashReplenishment
    {
        $replenishment = PettyCashReplenishment::findOrFail($replenishmentId);

        if (!in_array($replenishment->status, ['Posted', 'Reviewed'])) {
            throw new Exception('This replenishment is not open for audit editing.');
        }

        // dapat 'Liquidated' na noong snapshot mismo - kung 'Unliquidated' pa noon,
        // susunod na replenishment cycle ang masasakop nito kahit na-liquidate na
        // mamaya sa live, hindi ito
        $belongsToReplenishment = PettyCashReplenishmentSnapshot::where('petty_cash_replenishment_id', $replenishment->id)
            ->where('petty_cash_issuance_id', $issuanceId)
            ->where('status_at_snapshot', 'Liquidated')
            ->exists();

        if (!$belongsToReplenishment) {
            throw new Exception('This cash issuance is not part of the selected replenishment.');
        }

        return $replenishment;
    }

    // kapag na-correct ng auditor ang isang liquidation amount, dapat sumabay ang
    // overunder ng issuance, ang cash fund ng custodian (delta lang, hindi buong halaga),
    // at ang mga total ng replenishment - kung hindi, yung lumang mali/kulang na numero
    // pa rin ang madadala papunta sa Department Fund settlement pagka-Audited na.
    private function syncIssuanceAndReplenishmentAfterAuditEdit(PettyCashIssuance $issuance, PettyCashReplenishment $replenishment): void
    {
        $newTotalLiquidated = PettyCashLiquidation::where('petty_cash_issuance_id', $issuance->id)->sum('amount');
        $newOverunder = round($newTotalLiquidated - $issuance->amount, 2);
        $oldOverunder = round((float) ($issuance->overunder_amount ?? 0), 2);
        $delta = round($newOverunder - $oldOverunder, 2);

        if ($delta !== 0.0) {
            $custodian = PettyCashCustodian::lockForUpdate()->find($replenishment->petty_cash_custodian_id);
            if ($custodian) {
                // diff > 0 dati nang ibinawas sa fund (sobra sa inissue), kaya kung tumaas pa
                // ang diff (positive delta) ibawas din yung dagdag; kung bumaba (negative delta)
                // ibalik yung sobra sa fund. Same guard sa postLiquidation: bawal umabot sa negative.
                $newBalance = round((float) $custodian->remaining_cash_fund - $delta, 2);

                if ($newBalance < 0) {
                    throw new Exception(
                        'This change would push the remaining cash fund to ₱' . number_format($newBalance, 2) .
                        ', which exceeds the available balance of ₱' . number_format($custodian->remaining_cash_fund, 2) . '.'
                    );
                }

                $custodian->decrement('remaining_cash_fund', $delta);
            }
        }

        $issuance->update(['overunder_amount' => $newOverunder]);

        PettyCashReplenishmentSnapshot::where('petty_cash_replenishment_id', $replenishment->id)
            ->where('petty_cash_issuance_id', $issuance->id)
            ->update(['overunder_amount' => $newOverunder]);

        $snapshots = PettyCashReplenishmentSnapshot::where('petty_cash_replenishment_id', $replenishment->id)->get();

        $replenishment->update([
            'total_cash_issuance_amount' => $snapshots->sum(fn($s) => (float) $s->amount),
            'total_overunder_amount'     => $snapshots->sum(fn($s) => (float) ($s->overunder_amount ?? 0)),
            'total_replenishment_amount' => $snapshots->where('status_at_snapshot', 'Liquidated')
                ->sum(fn($s) => (float) $s->amount + (float) ($s->overunder_amount ?? 0)),
        ]);
    }

    public function auditCreateLiquidation(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'petty_cash_issuance_id'      => 'required|exists:petty_cash_issuances,id',
                'petty_cash_replenishment_id' => 'required|exists:petty_cash_replenishments,id',
                'liquidation_type'            => 'required|string',
                'reference_document_number'   => 'required|string|max:50',
                'reference_document_date'     => 'required|date|before_or_equal:today',
                'description'                 => 'required|string|max:250',
                'supplier_vendor'             => 'required|string|max:150',
                'amount'                      => 'required|numeric|min:0',
                'expense_type_id'             => 'required|exists:expense_types,id',
                'reason'                      => 'required|string|max:250',
                'attachments'                 => 'required|array|min:1',
                'attachments.*'               => 'required|file|mimetypes:image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/svg+xml,application/pdf',
            ]);

            $replenishment = $this->resolveReplenishmentForAuditEdit($request->petty_cash_replenishment_id, $request->petty_cash_issuance_id);
            $issuance = PettyCashIssuance::findOrFail($request->petty_cash_issuance_id);

            $count = PettyCashLiquidation::withTrashed()
                ->where('petty_cash_issuance_id', $issuance->id)
                ->count();

            $liquidationId = 'LIQ' . ($count + 1) . '-' . $issuance->pcv_number;

            $liquidation = PettyCashLiquidation::create([
                'liquidation_id'               => $liquidationId,
                'petty_cash_issuance_id'       => $issuance->id,
                'date'                         => now()->toDateString(),
                'liquidation_type'             => $request->liquidation_type,
                'requested_by_employee_number' => $issuance->requested_by_employee_number,
                'reference_document_number'    => $request->reference_document_number,
                'reference_document_date'      => $request->reference_document_date,
                'description'                  => $request->description,
                'supplier_vendor'              => $request->supplier_vendor,
                'amount'                       => $request->amount,
                'expense_type_id'              => $request->expense_type_id,
                'created_by_id'                => auth()->id(),
            ]);

            $this->saveAttachments($liquidation, $request);

            PettyCashLiquidationAuditLog::create([
                'petty_cash_liquidation_id'    => $liquidation->id,
                'petty_cash_replenishment_id'  => $replenishment->id,
                'action'                       => 'created',
                'reason'                       => $request->reason,
                'old_values'                   => null,
                'new_values'                   => $liquidation->only([
                    'liquidation_type', 'reference_document_number', 'reference_document_date',
                    'description', 'supplier_vendor', 'amount', 'expense_type_id',
                ]),
                'performed_by_id'              => auth()->id(),
            ]);

            $this->syncIssuanceAndReplenishmentAfterAuditEdit($issuance, $replenishment);

            DB::commit();
            return $this->sendResponse(['message' => 'Liquidation added successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function auditEditLiquidation(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'petty_cash_replenishment_id' => 'required|exists:petty_cash_replenishments,id',
                'liquidation_type'            => 'required|string',
                'reference_document_number'   => 'required|string|max:50',
                'reference_document_date'     => 'required|date|before_or_equal:today',
                'description'                 => 'required|string|max:250',
                'supplier_vendor'             => 'required|string|max:150',
                'amount'                      => 'required|numeric|min:0',
                'expense_type_id'             => 'required|exists:expense_types,id',
                'reason'                      => 'required|string|max:250',
                'attachments.*'               => 'file|mimetypes:image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp,image/svg+xml,application/pdf',
            ]);

            $liquidation = PettyCashLiquidation::findOrFail($id);
            $replenishment = $this->resolveReplenishmentForAuditEdit($request->petty_cash_replenishment_id, $liquidation->petty_cash_issuance_id);

            $oldValues = $liquidation->only([
                'liquidation_type', 'reference_document_number', 'reference_document_date',
                'description', 'supplier_vendor', 'amount', 'expense_type_id',
            ]);

            $liquidation->update([
                'liquidation_type'          => $request->liquidation_type,
                'reference_document_number' => $request->reference_document_number,
                'reference_document_date'   => $request->reference_document_date,
                'description'               => $request->description,
                'supplier_vendor'           => $request->supplier_vendor,
                'amount'                    => $request->amount,
                'expense_type_id'           => $request->expense_type_id,
                'updated_by_id'             => auth()->id(),
            ]);

            // alisin yung mga tinanggal na attachment
            if ($request->filled('removed_attachment_ids')) {
                $storage = Storage::disk('hr_system_folder');
                $removedIds = is_array($request->removed_attachment_ids)
                    ? $request->removed_attachment_ids
                    : explode(',', $request->removed_attachment_ids);

                foreach ($removedIds as $attId) {
                    $att = PettyCashLiquidationAttachment::find($attId);
                    if ($att) {
                        if ($storage->exists($att->file_path)) $storage->delete($att->file_path);
                        $att->delete();
                    }
                }
            }

            $remainingCount = PettyCashLiquidationAttachment::where('petty_cash_liquidation_id', $liquidation->id)->count();
            $incomingCount  = $request->hasFile('attachments') ? count($request->file('attachments')) : 0;

            if ($remainingCount + $incomingCount < 1) {
                DB::rollBack();
                return $this->sendError(['message' => 'At least one attachment is required.']);
            }

            $this->saveAttachments($liquidation, $request);

            PettyCashLiquidationAuditLog::create([
                'petty_cash_liquidation_id'   => $liquidation->id,
                'petty_cash_replenishment_id' => $replenishment->id,
                'action'                      => 'updated',
                'reason'                      => $request->reason,
                'old_values'                  => $oldValues,
                'new_values'                  => $liquidation->only([
                    'liquidation_type', 'reference_document_number', 'reference_document_date',
                    'description', 'supplier_vendor', 'amount', 'expense_type_id',
                ]),
                'performed_by_id'             => auth()->id(),
            ]);

            $issuance = PettyCashIssuance::findOrFail($liquidation->petty_cash_issuance_id);
            $this->syncIssuanceAndReplenishmentAfterAuditEdit($issuance, $replenishment);

            DB::commit();
            return $this->sendResponse(['message' => 'Liquidation updated successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getLiquidationAuditLogs($replenishmentId)
    {
        try {
            $logs = PettyCashLiquidationAuditLog::with(['liquidation', 'performedBy'])
                ->where('petty_cash_replenishment_id', $replenishmentId)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->sendResponse(['logs' => $logs]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function voidLiquidation($id)
    {
        try {
            $liquidation = PettyCashLiquidation::findOrFail($id);

            // para pwede pa ulit i-import pag na-void na, unique kasi yung cshub_item_id column
            if ($liquidation->cshub_trans_no || $liquidation->cshub_item_id) {
                $liquidation->update([
                    'cshub_trans_no' => null,
                    'cshub_item_id'  => null,
                ]);
            }

            $liquidation->delete();

            return $this->sendResponse(['message' => 'Liquidation voided successfully.']);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function postLiquidation(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $issuance = PettyCashIssuance::findOrFail($id);

            if ($issuance->status !== 'Unliquidated') {
                return $this->sendError(['message' => 'Only Unliquidated issuances can be posted.']);
            }

            $custodian = PettyCashCustodian::where('user_id', auth()->id())
                ->where('status', 'active')
                ->first();

            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.']);
            }

            if ($this->hasBlockingReplenishmentToday($custodian)) {
                return $this->sendError(['message' => 'A replenishment created today is still Pending or Posted. You can post liquidation once it is Replenished, or starting tomorrow.']);
            }

            $hasLiquidations = PettyCashLiquidation::where('petty_cash_issuance_id', $issuance->id)->exists();

            if (!$hasLiquidations) {
                return $this->sendError(['message' => 'No liquidation records found. Please add at least one liquidation before posting.']);
            }

            $totalLiquidated = PettyCashLiquidation::where('petty_cash_issuance_id', $issuance->id)->sum('amount');

            $diff = round($totalLiquidated - $issuance->amount, 2);

            if ($diff > 0) {
                // sobra sa inissue, ibabawas sa fund
                if ($diff > $custodian->remaining_cash_fund) {
                    return $this->sendError([
                        'message' => 'The excess liquidation amount of ₱' . number_format($diff, 2) .
                            ' exceeds the remaining cash fund of ₱' . number_format($custodian->remaining_cash_fund, 2) . '.',
                    ]);
                }
                $custodian->decrement('remaining_cash_fund', $diff);

            } elseif ($diff < 0) {
                // kulang sa inissue, kailangan ng denomination para sa sukli
                $changeAmount = abs($diff);

                $request->validate([
                    'denominations'            => 'required|array|min:1',
                    'denominations.*.value'    => 'required|numeric|min:0.01',
                    'denominations.*.quantity' => 'required|integer|min:1',
                ]);

                $denominationTotal = round(
                    collect($request->denominations)->sum(fn($d) => $d['value'] * $d['quantity']),
                    2
                );

                if (abs($denominationTotal - $changeAmount) > 0.01) {
                    return $this->sendError([
                        'message' => 'Denomination total of ₱' . number_format($denominationTotal, 2) .
                            ' does not match the change amount of ₱' . number_format($changeAmount, 2) . '.',
                    ]);
                }

                $custodian->increment('remaining_cash_fund', $changeAmount);
            }

            $issuance->update([
                'status'              => 'Liquidated',
                'unliquidated_amount' => 0,
                'overunder_amount'    => $diff,
                'liquidated_at'       => now(),
                'updated_by_id'       => auth()->id(),
            ]);

            DB::commit();
            return $this->sendResponse(['message' => 'Cash issuance posted as liquidated successfully.']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    private function fetchCSHubPettyCash($type, $approvedFrom, $approvedTo)
    {
        $baseUrl = env('CS_HUB_URL_LINK');
        $user = env('CS_HUB_ADMIN_EMAIL');
        $password = env('CS_HUB_ADMIN_PASSWORD');
        $company = env('COMPANY_NAME');

        if (!$baseUrl || !$user || !$password || !$company) {
            throw new Exception('CS Hub integration is not configured properly.');
        }

        $response = Http::withBasicAuth($user, $password)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->get(rtrim($baseUrl, '/') . '/get-petty-cash-transactions', [
                'type'          => $type,
                'approved_from' => $approvedFrom,
                'approved_to'   => $approvedTo,
                'company'       => $company,
            ]);

        if (!$response->successful()) {
            $message = $response->json('data.message') ?: $response->body();
            throw new Exception('Failed to fetch CS Hub transactions: ' . $message);
        }

        return $response->json('data.transactions') ?: [];
    }

    // tinatanggal yung /api kasi public web root ng cshub yung file path nila
    private function cshubFileUrl($relativePath)
    {
        $baseUrl = env('CS_HUB_URL_LINK');
        $fileBase = rtrim(preg_replace('#/api/?$#', '', $baseUrl), '/');
        return $fileBase . '/' . ltrim($relativePath, '/');
    }

    private function resolveExpenseTypeId($name)
    {
        if (!$name) return null;
        return ExpenseType::whereRaw('LOWER(description) = ?', [strtolower(trim($name))])->value('id');
    }

    // isang set lang ng attachments per cshub trans_no, kaya kinokopya sa bawat
    // liquidation row na nagmula sa mga liquidation_items ng parehong trans_no
    private function importCSHubAttachments(PettyCashLiquidation $liquidation, array $attachments): void
    {
        foreach ($attachments as $att) {
            $url = !empty($att['file_url'])
                ? $att['file_url']
                : (isset($att['file_path']) ? $this->cshubFileUrl($att['file_path']) : null);

            if (!$url) continue;

            PettyCashLiquidationAttachment::create([
                'petty_cash_liquidation_id' => $liquidation->id,
                'file_path'                 => $url,
                'file_name'                 => $att['original_filename'] ?? basename($att['file_path'] ?? $url),
                'source'                    => 'CSHUB',
            ]);
        }
    }

    // pinapalitan ang isang cshub liquidation transaction (trans_no) papuntang
    // isang candidate row per liquidation_item (may sariling expense type at
    // supplier); kung walang liquidation_items pa (lumang transaction), isang
    // legacy row lang gamit ang buong actual_amount_spent, tulad ng dati
    private function flattenCSHubLiquidationCandidates($t)
    {
        $items = $t['liquidation_items'] ?? [];
        $attachments = $t['liquidation_attachments'] ?? [];
        $rows = [];

        if (empty($items)) {
            $alreadyImported = PettyCashLiquidation::where('cshub_trans_no', $t['trans_no'])
                ->whereNull('cshub_item_id')
                ->exists();

            $rows[] = [
                'selection_key'          => 'legacy:' . $t['id'],
                'cshub_id'               => $t['id'] ?? null,
                'cshub_item_id'          => null,
                'trans_no'               => $t['trans_no'],
                'liquidation_date_filed' => $t['liquidation_date_filed'] ?? null,
                'expense_type'           => null,
                'supplier'               => null,
                'amount'                 => $t['actual_amount_spent'] ?? 0,
                'remarks'                => $t['liquidation_remarks'] ?? ($t['purpose'] ?? ''),
                'attachment_count'       => count($attachments),
                'already_imported'       => $alreadyImported,
                'importable'             => !$alreadyImported,
            ];
            return $rows;
        }

        foreach ($items as $item) {
            $alreadyImported = isset($item['id'])
                ? PettyCashLiquidation::where('cshub_item_id', $item['id'])->exists()
                : false;

            $rows[] = [
                'selection_key'          => 'item:' . $item['id'],
                'cshub_id'               => $t['id'] ?? null,
                'cshub_item_id'          => $item['id'] ?? null,
                'trans_no'               => $t['trans_no'],
                'liquidation_date_filed' => $t['liquidation_date_filed'] ?? null,
                'expense_type'           => $item['expense_type'] ?? null,
                'supplier'               => $item['supplier'] ?? null,
                'amount'                 => $item['amount'] ?? 0,
                'remarks'                => $item['remarks'] ?: ($t['liquidation_remarks'] ?? ''),
                'attachment_count'       => count($attachments),
                'already_imported'       => $alreadyImported,
                'importable'             => !$alreadyImported,
            ];
        }

        return $rows;
    }

    // same trans_no din ginagamit para mahanap yung liquidation nito sa cshub
    public function getCSHubLiquidationCandidates(Request $request, $issuanceId)
    {
        try {
            $issuance = PettyCashIssuance::findOrFail($issuanceId);

            if (!$issuance->cshub_trans_no) {
                return $this->sendError(['message' => 'This cash issuance was not imported from CS Hub.']);
            }

            $approvedFrom = $request->approved_from ?: now()->subMonths(3)->startOfMonth()->toDateString();
            $approvedTo = $request->approved_to ?: now()->toDateString();

            $transactions = $this->fetchCSHubPettyCash('PETTY_CASH_LIQUIDATION', $approvedFrom, $approvedTo);

            $candidates = [];
            foreach ($transactions as $t) {
                if (!isset($t['trans_no']) || $t['trans_no'] !== $issuance->cshub_trans_no) {
                    continue;
                }

                $candidates = array_merge($candidates, $this->flattenCSHubLiquidationCandidates($t));
            }

            return $this->sendResponse(['transactions' => $candidates]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function importCSHubLiquidations(Request $request, $issuanceId)
    {
        $request->validate([
            'selection_keys'   => 'required|array|min:1',
            'selection_keys.*' => 'required|string',
        ]);

        try {
            $issuance = PettyCashIssuance::findOrFail($issuanceId);

            if (!$issuance->cshub_trans_no) {
                return $this->sendError(['message' => 'This cash issuance was not imported from CS Hub.']);
            }

            $approvedFrom = $request->approved_from ?: now()->subMonths(3)->startOfMonth()->toDateString();
            $approvedTo = $request->approved_to ?: now()->toDateString();

            $transactions = $this->fetchCSHubPettyCash('PETTY_CASH_LIQUIDATION', $approvedFrom, $approvedTo);

            // i-index lahat ng candidate rows (item- or legacy-level) by selection_key
            $byKey = [];
            foreach ($transactions as $t) {
                if (!isset($t['trans_no']) || $t['trans_no'] !== $issuance->cshub_trans_no) {
                    continue;
                }
                foreach ($this->flattenCSHubLiquidationCandidates($t) as $row) {
                    $byKey[$row['selection_key']] = ['row' => $row, 'transaction' => $t];
                }
            }

            $results = [];

            foreach ($request->selection_keys as $key) {
                $match = $byKey[$key] ?? null;

                if (!$match) {
                    $results[] = [
                        'selection_key' => $key,
                        'success'       => false,
                        'message'       => 'Transaction is no longer available for import on CS Hub.',
                    ];
                    continue;
                }

                $row = $match['row'];
                $t = $match['transaction'];

                if ($row['already_imported']) {
                    $results[] = [
                        'selection_key' => $key,
                        'success'       => false,
                        'message'       => 'Already imported.',
                    ];
                    continue;
                }

                DB::beginTransaction();
                try {
                    $count = PettyCashLiquidation::withTrashed()
                        ->where('petty_cash_issuance_id', $issuance->id)
                        ->count();

                    $liquidationId = 'LIQ' . ($count + 1) . '-' . $issuance->pcv_number;

                    $liquidation = PettyCashLiquidation::create([
                        'liquidation_id'               => $liquidationId,
                        'cshub_trans_no'                => $row['trans_no'],
                        'cshub_id'                      => $row['cshub_id'],
                        'cshub_item_id'                 => $row['cshub_item_id'],
                        'source'                        => 'CSHUB',
                        'petty_cash_issuance_id'       => $issuance->id,
                        'date'                         => now()->toDateString(),
                        'liquidation_type'             => 'Cash Advance Liquidation',
                        'requested_by_employee_number' => $issuance->requested_by_employee_number,
                        'reference_document_number'    => $row['trans_no'],
                        'reference_document_date'      => $row['liquidation_date_filed'] ?: now()->toDateString(),
                        'description'                  => $row['remarks'],
                        'supplier_vendor'              => $row['supplier'],
                        'amount'                       => $row['amount'],
                        'expense_type_id'              => $this->resolveExpenseTypeId($row['expense_type']),
                        'created_by_id'                => auth()->id(),
                    ]);

                    $this->importCSHubAttachments($liquidation, $t['liquidation_attachments'] ?? []);

                    DB::commit();
                    $results[] = ['selection_key' => $key, 'success' => true, 'message' => 'Imported successfully.'];
                } catch (Exception $e) {
                    DB::rollBack();
                    $results[] = [
                        'selection_key' => $key,
                        'success'       => false,
                        'message'       => 'Failed to import liquidation: ' . $e->getMessage(),
                    ];
                }
            }

            return $this->sendResponse(['results' => $results]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
