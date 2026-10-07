<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashCustodian;
use App\Models\PettyCashDepartmentFundTransaction;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashTransactionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class DepartmentFundController extends Controller
{
    private function getActiveCustodian()
    {
        return PettyCashCustodian::where('user_id', auth()->id())
            ->where('status', 'active')
            ->with(['branches', 'user'])
            ->first();
    }

    public function getMyCustodianInfo()
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }
            return $this->sendResponse($custodian);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getTransactionTypes()
    {
        try {
            $types = PettyCashTransactionType::where('status', 'active')->orderBy('name')->get();
            return $this->sendResponse(['types' => $types]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    // ginagamit ng Report ID dropdown pag transaction type ay Replenishment
    public function getPostedReplenishments()
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            $replenishments = PettyCashReplenishment::where('petty_cash_custodian_id', $custodian->id)
                ->where('status', 'Audited')
                ->orderBy('date')
                ->get(['id', 'replenishment_id', 'total_replenishment_amount']);

            return $this->sendResponse(['replenishments' => $replenishments]);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function getTransactions(Request $request)
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            $perPage = $request->per_page ?? 10;

            $transactions = PettyCashDepartmentFundTransaction::where('custodian_id', $custodian->id)
                ->with(['transactionType', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->sendResponse($transactions);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function addTransaction(Request $request)
    {
        try {
            $custodian = $this->getActiveCustodian();
            if (!$custodian) {
                return $this->sendError(['message' => 'You are not an active petty cash custodian.'], 403);
            }

            $type = PettyCashTransactionType::findOrFail($request->transaction_type_id);
            $isReplenishment = $type->name === 'Replenishment';

            $rules = [
                'transaction_type_id' => 'required|exists:petty_cash_transaction_types,id',
                'reason'              => 'required|string|max:1000',
            ];

            if ($isReplenishment) {
                // dito galing yung Report ID: replenishment_id ng piniling Audited replenishment
                $rules['report_id'] = 'required|string';
            } elseif ($type->report_id_required) {
                $rules['report_id'] = 'required|string|max:255';
            } else {
                $rules['report_id'] = 'nullable|string|max:255';
            }

            if (!$isReplenishment) {
                if ($type->allow_negative) {
                    // pwede negative, wala nang min floor
                    $rules['cash_fund'] = $type->cash_fund_required ? 'required|numeric' : 'nullable|numeric';
                } else {
                    $rules['cash_fund'] = $type->cash_fund_required ? 'required|numeric|min:0.01' : 'nullable|numeric|min:0';
                }
            }

            $request->validate($rules);

            DB::transaction(function () use ($request, $custodian, $type, $isReplenishment) {
                $replenishment = null;
                $cashFund = (float) ($request->cash_fund ?? 0);

                // amount galing mismo sa replenishment record, hindi sa input ng user
                if ($isReplenishment) {
                    $replenishment = PettyCashReplenishment::where('replenishment_id', $request->report_id)
                        ->where('petty_cash_custodian_id', $custodian->id)
                        ->where('status', 'Audited')
                        ->lockForUpdate()
                        ->first();

                    if (!$replenishment) {
                        throw new Exception('Selected replenishment is invalid or already replenished.');
                    }

                    $cashFund = (float) $replenishment->total_replenishment_amount;
                }

                PettyCashDepartmentFundTransaction::create([
                    'custodian_id'        => $custodian->id,
                    'transaction_type_id' => $request->transaction_type_id,
                    'report_id'           => $request->report_id,
                    'cash_fund'           => $cashFund !== 0.0 ? $cashFund : ($request->cash_fund ?: null),
                    'reason'              => $request->reason,
                    'created_by_id'       => auth()->id(),
                ]);

                if ($replenishment) {
                    $replenishment->update(['status' => 'Replenished']);
                }

                if ($cashFund != 0) {
                    // pag 'both' yung direction, sign ng input mismo ang nagsasabi kung add o less
                    $isAdd = $type->effect_direction === 'both'
                        ? $cashFund > 0
                        : $type->effect_direction === 'add';
                    $amount = abs($cashFund);

                    if ($isAdd) {
                        if (in_array($type->effect_target, ['remaining_cash_fund', 'both'])) {
                            $custodian->increment('remaining_cash_fund', $amount);
                        }
                        if (in_array($type->effect_target, ['total_cash_fund', 'both'])) {
                            $custodian->increment('total_cash_fund', $amount);
                        }
                    } else {
                        if (in_array($type->effect_target, ['remaining_cash_fund', 'both'])) {
                            $custodian->decrement('remaining_cash_fund', $amount);
                        }
                        if (in_array($type->effect_target, ['total_cash_fund', 'both'])) {
                            $custodian->decrement('total_cash_fund', $amount);
                        }
                    }
                }
            });

            return $this->sendResponse(['message' => 'Transaction added successfully.']);
        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }
}
