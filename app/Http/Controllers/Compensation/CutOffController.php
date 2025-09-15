<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Models\Cutoff;
use Illuminate\Http\Request;
use Exception;
use DB;

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

            DB::commit();
            return $this->sendResponse(["message" => "Cut off created successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editCutOff(Request $request, $id) {
        try {
            DB::beginTransaction();

            DB::commit();
            return $this->sendResponse(["message" => "Cut off edited successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
