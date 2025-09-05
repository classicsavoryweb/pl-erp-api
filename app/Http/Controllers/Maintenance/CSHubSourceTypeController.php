<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\CSHubSourceTypeExport;
use App\Http\Controllers\Controller;
use App\Models\CSHubSourceType;
use Exception;
use Illuminate\Http\Request;

class CSHubSourceTypeController extends Controller
{

    public function getTransType(Request $request)
    {
        try {
            $transType = CSHubSourceType::where("status", "active")
            ->whereIn("source_type", ["Transaction", "N/A"])
            ->get();
            return $this->sendResponse(["transaction_type" => $transType]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getExamType(Request $request) {
        try {
            $examType = CSHubSourceType::where("status", "active")
                ->whereIn("source_type", ["Exam", "N/A"])
                ->get();
            return $this->sendResponse(["exam_type" => $examType]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
