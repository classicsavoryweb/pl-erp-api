<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\Transactions\IncorrectLogExport;
use App\Models\Adjustment;
use Exception;
use DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Str;
use Carbon\Carbon;

class IncorrectLogsController extends Controller
{
    public function getAllIncorrectLogs(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $incorrect_logs = Adjustment::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $incorrect_logs = $incorrect_logs->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            })
                            ->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $incorrect_logs = $incorrect_logs->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $incorrect_logs = $incorrect_logs->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $incorrect_logs = $incorrect_logs->where("status", $request->status);
            }

            if ($request->has(['branch_id'])) {
                $incorrect_logs = $incorrect_logs->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            $incorrect_logs = $incorrect_logs->with([
                "employee",
                "employee.current_branch",
            ])
            ->orderBy("id","DESC");

            if ($request->has(["export"]) && $request->export) {
                $incorrect_logs = $incorrect_logs->get();
                return Excel::download(new IncorrectLogExport($incorrect_logs), "incorrect_logs.xls");
            }
            
            $incorrect_logs = $incorrect_logs->paginate($perPage);

            return $this->sendResponse($incorrect_logs);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllIncorrectLogs(Request $request) {
        $request["export"] = true;

        return $this->getAllIncorrectLogs($request);
    }

    public function getIncorrectLog(Request $request) {
        try {
            $incorrent_log = Adjustment::where("status", "approved")->get();
            return $this->sendResponse(["status" => $incorrent_log]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}