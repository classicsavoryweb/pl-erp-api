<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Jobs\TransferLogsToTimeKeeping;
use App\Models\BiotimeLog;
use Exception;
use Illuminate\Http\Request;
use Str;
use DB;

class ActualTimeController extends Controller
{
    public function getAllBiotimeTransactions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $bioTimeLogs = BiotimeLog::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $bioTimeLogs = $bioTimeLogs->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $bioTimeLogs = $bioTimeLogs->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    if ($column === "full_name") {
                        $bioTimeLogs = $bioTimeLogs->whereHas("employee", function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $bioTimeLogs = $bioTimeLogs->where($column, 'LIKE', "%$keyword%");
                    }
                }
            }

            if ($request->has(['branch_id'])) {
                $bioTimeLogs = $bioTimeLogs->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            if ($request->has(["date_from", "date_to"])) {
                $bioTimeLogs = $bioTimeLogs->whereBetween('date', [$request->date_from, $request->date_to]);
            }

            $bioTimeLogs = $bioTimeLogs->with([
                "employee",
                "punches",
                "punches.branch"
            ])
            ->paginate($perPage);

            return $this->sendResponse($bioTimeLogs);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
