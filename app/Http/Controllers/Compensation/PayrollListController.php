<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Models\PayrollList;
use Illuminate\Http\Request;
use Exception;
use DB;
use Str;

class PayrollListController extends Controller
{
    public function getAllPayrollList(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $payrollList = PayrollList::with(["employee",]);

            if ($request->has(["branch_id"])) {
                $payrollList = $payrollList->whereHas("employee", function ($query) use ($request) {
                    return $query->where("branch_id", $request->branch_id);
                });
            }

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $payrollList = $payrollList->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $payrollList = $payrollList->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $payrollList = $payrollList->where($column, 'LIKE', "%$keyword%");
                }
            }

            $payrollList = $payrollList->paginate($perPage);

            return $this->sendResponse($payrollList);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
