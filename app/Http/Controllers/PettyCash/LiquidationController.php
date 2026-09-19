<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashCustodian;
use App\Models\PettyCashIssuance;
use App\Models\PettyCashLiquidation;
use App\Models\PettyCashLiquidationAttachment;
use App\Models\PettyCashReplenishment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;

class LiquidationController extends Controller
{
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

            $liquidations = PettyCashLiquidation::with(['expenseType', 'createdBy', 'attachments'])
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
                'amount'                    => 'required|numeric|min:0.01',
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
                'amount'                    => 'required|numeric|min:0.01',
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

    public function voidLiquidation($id)
    {
        try {
            $liquidation = PettyCashLiquidation::findOrFail($id);

            // para pwede pa ulit i-import pag na-void na, unique kasi yung column
            if ($liquidation->cshub_trans_no) {
                $liquidation->update(['cshub_trans_no' => null]);
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

            // bawal mag-liquidate habang may hindi pa Voided/Replenished, baka mahabol na sa snapshot
            $hasUnsettledReplenishment = PettyCashReplenishment::where('petty_cash_custodian_id', $custodian->id)
                ->whereNotIn('status', ['Voided', 'Replenished'])
                ->exists();

            if ($hasUnsettledReplenishment) {
                return $this->sendError(['message' => 'A replenishment is still Pending or Posted. You can post liquidation once it is Replenished.']);
            }

            $totalLiquidated = PettyCashLiquidation::where('petty_cash_issuance_id', $issuance->id)->sum('amount');

            if ($totalLiquidated <= 0) {
                return $this->sendError(['message' => 'No liquidation records found. Please add at least one liquidation before posting.']);
            }

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

                $alreadyImported = PettyCashLiquidation::where('cshub_trans_no', $t['trans_no'])->exists();
                $attachments = isset($t['liquidation_attachments']) ? $t['liquidation_attachments'] : [];

                $candidates[] = [
                    'cshub_id'              => isset($t['id']) ? $t['id'] : null,
                    'trans_no'               => $t['trans_no'],
                    'liquidation_date_filed' => isset($t['liquidation_date_filed']) ? $t['liquidation_date_filed'] : null,
                    'actual_amount_spent'    => isset($t['actual_amount_spent']) ? $t['actual_amount_spent'] : 0,
                    'liquidation_remarks'    => isset($t['liquidation_remarks']) ? $t['liquidation_remarks'] : '',
                    'attachment_count'       => count($attachments),
                    'already_imported'       => $alreadyImported,
                    'importable'             => !$alreadyImported,
                ];
            }

            return $this->sendResponse(['transactions' => $candidates]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function importCSHubLiquidations(Request $request, $issuanceId)
    {
        $request->validate([
            'cshub_ids'   => 'required|array|min:1',
            'cshub_ids.*' => 'required',
        ]);

        try {
            $issuance = PettyCashIssuance::findOrFail($issuanceId);

            if (!$issuance->cshub_trans_no) {
                return $this->sendError(['message' => 'This cash issuance was not imported from CS Hub.']);
            }

            $approvedFrom = $request->approved_from ?: now()->subMonths(3)->startOfMonth()->toDateString();
            $approvedTo = $request->approved_to ?: now()->toDateString();

            $transactions = $this->fetchCSHubPettyCash('PETTY_CASH_LIQUIDATION', $approvedFrom, $approvedTo);
            $byId = [];
            foreach ($transactions as $t) {
                if (isset($t['id'])) {
                    $byId[$t['id']] = $t;
                }
            }

            $results = [];

            foreach ($request->cshub_ids as $cshubId) {
                $t = isset($byId[$cshubId]) ? $byId[$cshubId] : null;

                if (!$t || !isset($t['trans_no']) || $t['trans_no'] !== $issuance->cshub_trans_no) {
                    $results[] = [
                        'cshub_id' => $cshubId,
                        'success'  => false,
                        'message'  => 'Transaction is no longer available for import on CS Hub.',
                    ];
                    continue;
                }

                if (PettyCashLiquidation::where('cshub_trans_no', $t['trans_no'])->exists()) {
                    $results[] = [
                        'cshub_id' => $cshubId,
                        'success'  => false,
                        'message'  => 'Already imported.',
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
                        'cshub_trans_no'                => $t['trans_no'],
                        'cshub_id'                      => $t['id'],
                        'source'                        => 'CSHUB',
                        'petty_cash_issuance_id'       => $issuance->id,
                        'date'                         => now()->toDateString(),
                        'liquidation_type'             => 'Cash Advance Liquidation',
                        'requested_by_employee_number' => $issuance->requested_by_employee_number,
                        'reference_document_number'    => $t['trans_no'],
                        'reference_document_date'      => isset($t['liquidation_date_filed']) ? $t['liquidation_date_filed'] : now()->toDateString(),
                        'description'                  => isset($t['liquidation_remarks']) && $t['liquidation_remarks']
                            ? $t['liquidation_remarks']
                            : (isset($t['purpose']) ? $t['purpose'] : ''),
                        'supplier_vendor'              => null,
                        'amount'                       => isset($t['actual_amount_spent']) ? $t['actual_amount_spent'] : 0,
                        'expense_type_id'              => null,
                        'created_by_id'                => auth()->id(),
                    ]);

                    $attachments = isset($t['liquidation_attachments']) ? $t['liquidation_attachments'] : [];
                    foreach ($attachments as $att) {
                        if (!isset($att['file_path'])) continue;

                        PettyCashLiquidationAttachment::create([
                            'petty_cash_liquidation_id' => $liquidation->id,
                            'file_path'                 => $this->cshubFileUrl($att['file_path']),
                            'file_name'                 => isset($att['original_filename']) ? $att['original_filename'] : basename($att['file_path']),
                            'source'                    => 'CSHUB',
                        ]);
                    }

                    DB::commit();
                    $results[] = ['cshub_id' => $cshubId, 'success' => true, 'message' => 'Imported successfully.'];
                } catch (Exception $e) {
                    DB::rollBack();
                    $results[] = [
                        'cshub_id' => $cshubId,
                        'success'  => false,
                        'message'  => 'Failed to import liquidation: ' . $e->getMessage(),
                    ];
                }
            }

            return $this->sendResponse(['results' => $results]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
