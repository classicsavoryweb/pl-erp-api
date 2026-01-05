<?php

namespace App\Http\Controllers\Compensation;

use App\Exports\Compensation\EntryExport;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateEntry;
use App\Models\Entry;
use App\Models\Payroll;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use DB;
use Illuminate\Support\Facades\Auth;
use Str;

class EntryController extends Controller
{
    public function getAllEntries(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $entryQuery = Entry::with(['branch']);
            
            if ($request->has(["column_type", "key_word"])) {
                if ($request->column_type === "branch.name") {
                    $entryQuery->whereHas('branch', function($query) use ($request) {
                        $query->where('name', 'LIKE', "%{$request->key_word}%");
                    });
                } else {
                    $entryQuery->where($request->column_type, 'LIKE', "%{$request->key_word}%");
                }
            }

            if ($request->has(["export"]) && $request->export) {
                $entry = $entryQuery->get();
                return Excel::download(new EntryExport($entry), "entry.xls");
            }

            $entry = $entryQuery->paginate($perPage);

            return $this->sendResponse($entry);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function generateEntries(Request $request) {
        try {
            $request->validate([
                "pay_date" => "required",
                "date_from" => "required",
                "date_to" => "required",
            ]);

            $payroll = Payroll::with(["employee"])
            ->where("pay_date", $request->pay_date)
            ->where("start_date", $request->date_from)
            ->where("end_date", $request->date_to)
            ->get();

            $pay_date = $request->pay_Date;
            $start_date = $request->date_from;
            $end_Date = $request->date_from;

            GenerateEntry::dispatch($payroll, $pay_date, $start_date, $end_Date, Auth::user()->id);

            return $this->sendResponse(["message" => "Entry are in queue for processing."]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllEntries(Request $request) {
        $request["export"] = true;

        return $this->getAllEntries($request);
    }
}
