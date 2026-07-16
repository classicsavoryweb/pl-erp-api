<?php

namespace App\Http\Controllers\PettyCash;

use App\Http\Controllers\Controller;
use App\Models\PettyCashCustodian;
use App\Models\PettyCashDepartmentFundTransaction;
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

            $rules = [
                'transaction_type_id' => 'required|exists:petty_cash_transaction_types,id',
                'reason'              => 'required|string|max:1000',
            ];

            if ($type->cash_fund_required) {
                $rules['cash_fund'] = 'required|numeric|min:0.01';
            } else {
                $rules['cash_fund'] = 'nullable|numeric|min:0';
            }

            if ($type->report_id_required) {
                $rules['report_id'] = 'required|string|max:255';
            } else {
                $rules['report_id'] = 'nullable|string|max:255';
            }

            $request->validate($rules);

            DB::transaction(function () use ($request, $custodian, $type) {
                PettyCashDepartmentFundTransaction::create([
                    'custodian_id'        => $custodian->id,
                    'transaction_type_id' => $request->transaction_type_id,
                    'report_id'           => $request->report_id,
                    'cash_fund'           => $request->cash_fund,
                    'reason'              => $request->reason,
                    'created_by_id'       => auth()->id(),
                ]);

                $amount = (float) ($request->cash_fund ?? 0);
                if ($amount > 0) {
                    if ($type->effect_direction === 'add') {
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
