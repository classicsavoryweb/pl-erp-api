<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Models\ConverterDetail;
use App\Models\ConverterMain;
use App\Models\Employee;
use App\Models\Term;
use Illuminate\Http\Request;
use Exception;
use DB;
use Illuminate\Support\Facades\Auth;

class ConverterController extends Controller
{
    public function getAllConverterDetails(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $converters = ConverterDetail::with(["employee", "transaction_type", "branch"])            
            ->orderBy("created_at", "DESC")
            ->paginate($perPage);

            return $this->sendResponse($converters);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getAllConverterMain(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $converters = ConverterMain::with(["added_by", "posted_by", "generated_by", "voided_by"])
            ->orderBy("created_at", "DESC")
            ->paginate($perPage);

            return $this->sendResponse($converters);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createConverterDetails(Request $request) {
        try {
            DB::beginTransaction();

            $request->validate([
                "employee_id" => "required",
                "amount" => "required",
                "transaction_type_id" => "required",
                "terms_id" => "required"
            ]);

            $employee = Employee::find($request->employee_id);
            $terms = Term::find($request->terms_id);

            ConverterDetail::create([
                "employee_id" => $employee->id,
                "branch_id" => $employee->current_branch->id,
                "amount" => $request->amount,
                "transaction_type_id" => $request->transaction_type_id,
                "terms" => $terms->cutoff,
                "remarks" => $request->remarks ?? null,
                "added_by" => Auth::user()->id,
                "status" => "Pending",
                "source" => "Manual"
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Converter details successfully created"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editConverterDetails(Request $request, $detailId) {
        try {
            DB::beginTransaction();

            $request->validate([
                "amount" => "required",
            ]);

            $detail = ConverterDetail::find($detailId);

            if ($detail) {
                $detail->update([
                    "amount" => $request->amount,
                ]);
                DB::commit();
                return $this->sendResponse(["message" => "Converter details updated successfully"]);
            }

            return $this->sendError(["message" => "Converter details not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function voidConverterDetails($detailId) {
        try {
            DB::beginTransaction();

            $detail = ConverterDetail::find($detailId);

            if ($detail) {
                $detail->update([
                    "status" => "Voided"
                ]);

                DB::commit();
                return $this->sendResponse(["message" => "Converter details voided successfully"]);
            }

            return $this->sendError(["message" => "Converter details not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createConverterMain(Request $request) {
        try {
            DB::beginTransaction();

            DB::commit();
            return $this->sendResponse(["message" => "Batch successfully created"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
