<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Models\BranchAdditionalIncome;
use App\Models\Cutoff;
use Illuminate\Http\Request;
use Exception;
use DB;

class BranchAdditionalIncomeController extends Controller
{
    public function getAllBranchAdditionalIncome(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;
            $additionalIncome = BranchAdditionalIncome::with(["branch"]);

            if ($request->has(["date_from", "date_to"])) {
                $additionalIncome = $additionalIncome->whereBetween("pay_date", [$request->date_from, $request->date_to]);
            }

            if ($request->has(["branch_id"])) {
                $additionalIncome = $additionalIncome->where("branch_id", $request->branch_id);
            }

            $additionalIncome = $additionalIncome
            ->orderBy("pay_date", "DESC")
            ->paginate($perPage);

            return $this->sendResponse($additionalIncome);
        } catch (Exception $e) {
            return $this->sendError([
                "message" => $e->getMessage()
            ]);
        }
    }

    public function updateOrCreateIncome(Request $request) {
        try {
            DB::beginTransaction();

            $request->validate([
                "branch_id" => "required",
                "amount" => "required"
            ]);

            $activeCutoff = Cutoff::where("status", "active")->first();

            if ($activeCutoff) {
                BranchAdditionalIncome::updateOrCreate([
                    "branch_id" => $request->branch_id,
                    "pay_date" => $activeCutoff->pay_date   
                ],[
                    "amount" => $request->amount
                ]);

                DB::commit();
                return $this->sendResponse(["message" => "Additional income saved successfully"]);
            }
            
            DB::rollBack();
            return $this->sendError(["message" => "No active cut off"]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
