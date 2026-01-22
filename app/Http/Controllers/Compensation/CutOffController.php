<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFinalizeCutOff;
use App\Jobs\ProcessInitializeCutOff;
use App\Models\Cutoff;
use App\Models\PayrollList;
use Illuminate\Http\Request;
use Exception;
use DB;
use Illuminate\Support\Facades\Auth;

class CutOffController extends Controller
{
    public function getAllCutOffs(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $cutOffs = Cutoff::orderBy("id", "DESC")
            ->paginate($perPage);

            return $this->sendResponse($cutOffs);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createCutOff(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "cutoff" => "required",
                "year" => "required",
                "pay_date" => "required",
                "initialize" => "required",
                "part" => "required"
            ]);

            // $startDate = $request->year."-".$request->cutoff["start_time"];
            // $endDate = $request->year."-".$request->cutoff["end_time"];

            $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $request->year);
            $startDate = $dates["start_date"];
            $endDate = $dates["end_date"];
            $cutOff = $startDate."-".$endDate;

            $isExisting = Cutoff::where("cut_off", $cutOff)->first();
            if ($isExisting) {
                return $this->sendError(["message" => "Cutoff is already existing"]);
            }

            $initialized = false;
            $initializedJson = [];

            if ($request->initialize === "Yes") {
                $initialized = true;
                $initializedJson[] = [
                    "initialized_at" => now(),
                    "initialized_by" => Auth::user()->full_name
                ];
            }
            
            $createdCutOff = Cutoff::create([
                "cut_off" => $cutOff,
                "start_date" => $startDate,
                "end_date" => $endDate,
                "is_cutoff_initialized" => $initialized,
                "status" => "active",
                "pay_date" => $request->pay_date,
                "initializations" => json_encode($initializedJson),
                "part" => $request->part
            ]);

            Cutoff::whereNot("id", $createdCutOff->id)
            ->where("status", "active")
            ->update(["status" => "inactive"]);

            if ($initialized) {
                ProcessInitializeCutOff::dispatch($createdCutOff, Auth::id());
            }

            DB::commit();
            return $this->sendResponse(["message" => "Cut off created successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function initializeCutOff($id) {
        try {
            DB::beginTransaction();

            $createdCutOff = Cutoff::find($id);

            if ($createdCutOff) {                
                $initializedJson = [];

                if ($createdCutOff->initializations) {
                    $initializedJson = json_decode($createdCutOff->initializations, true);
                }

                $newJson = [
                    [
                        "initialized_at" => now(),
                        "initialized_by" => Auth::user()->full_name
                    ],
                    ...$initializedJson
                ];

                $createdCutOff->update([
                    "initializations" => json_encode($newJson)
                ]);

                //trigger a cut off initialization job
                ProcessInitializeCutOff::dispatch($createdCutOff, Auth::id());

                DB::commit();
                return $this->sendResponse([
                    "cut_off" => $createdCutOff,
                    "message" => "Cut off initialized successfully"
                ]);
            }

            return $this->sendError(["message" => "Cut off not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function finalizeCutOff($id) {
        try {
            DB::beginTransaction();

            $createdCutOff = Cutoff::find($id);

            if ($createdCutOff) {                
                $initializedJson = [];

                if ($createdCutOff->initializations) {
                    $initializedJson = json_decode($createdCutOff->initializations, true);
                }

                $newJson = [
                    [
                        "finalized_at" => now(),
                        "finalized_by" => Auth::user()->full_name
                    ],
                    ...$initializedJson
                ];

                $createdCutOff->update([
                    "is_cutoff_locked" => true,
                    "is_timekeeping_locked" => true,
                    "initializations" => json_encode($newJson)
                ]);

                $cutoffValue = $createdCutOff->cut_off;

                $payrollLists = PayrollList::where('is_hold', 1)->get();

                foreach ($payrollLists as $payrollList) {

                    $holdedCutoffs = [];

                    if (!empty($payrollList->holded_cutoffs)) {
                        $holdedCutoffs = json_decode($payrollList->holded_cutoffs, true) ?? [];
                    }

                    if (!in_array($cutoffValue, $holdedCutoffs)) {
                        $holdedCutoffs[] = $cutoffValue;
                    }

                    $payrollList->update([
                        'holded_cutoffs' => json_encode($holdedCutoffs),
                    ]);
                }

                //trigger a cut off initialization job
                ProcessFinalizeCutOff::dispatch($createdCutOff, Auth::user()->id);

                DB::commit();
                return $this->sendResponse([
                    "cut_off" => $createdCutOff,
                    "message" => "Cut off finalized successfully"
                ]);
            }

            return $this->sendError(["message" => "Cut off not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editCutOff(Request $request, $id) {
        try {
            DB::beginTransaction();
            $request->validate([
                "cutoff" => "required",
                "year" => "required",
                "pay_date" => "required",
                "initialize" => "required",
                "part" => "required"
            ]);

            $dates = $this->normalizeCutOff($request->cutoff["start_time"], $request->cutoff["end_time"], $request->year);
            $startDate = $dates["start_date"];
            $endDate = $dates["end_date"];
            $cutOff = $startDate."-".$endDate;

            $createdCutOff = Cutoff::find($id);

            if ($createdCutOff) {                
                $createdCutOff->update([
                    "cut_off" => $cutOff,
                    "start_date" => $startDate,
                    "end_date" => $endDate,
                    "status" => "active",
                    "pay_date" => $request->pay_date,
                    "part" => $request->part,
                ]);

                DB::commit();
                return $this->sendResponse(["message" => "Cut off updated successfully"]);
            }

            return $this->sendError(["message" => "Cut off not found"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getAllFinalizedCutOffs() {
        try {
            $cut_offs = Cutoff::where("is_cutoff_locked", 1)
            ->orderBy("pay_date", "DESC")
            ->get();

            return $this->sendResponse($cut_offs);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
