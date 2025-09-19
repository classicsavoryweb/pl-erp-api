<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Str;

class PayrollController extends Controller
{
    public function getAllPayroll(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;
            $currentCutOff = $this->getCurrentCutoff();
            $year = $request->year ?? Carbon::now()->year;

            $start_time = isset($request->cutoff["start_time"]) && $request->cutoff["start_time"]
                ? $year . '-' . $request->cutoff["start_time"]
                : $currentCutOff['start_time'];

            $end_time = isset($request->cutoff["end_time"]) && $request->cutoff["end_time"]
                ? $year . '-' . $request->cutoff["end_time"]
                : $currentCutOff['end_time'];

            $payroll = Payroll::with(["employee","employee.current_branch", "timekeeping_branch", "timekeeping_summary", "lates"])
            ->where("cut_off", $start_time."-".$end_time);

            if ($request->has(["branch_id"])) {
                $payroll = $payroll->whereHas("employee", function ($query) use ($request) {
                    return $query->where("branch_id", $request->branch_id);
                });
            }

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $payroll = $payroll->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $payroll = $payroll->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $payroll = $payroll->where($column, 'LIKE', "%$keyword%");
                }
            }

            

            // if ($request->has(["export"]) && $request->export) {
            //     $payroll = $payroll->get();
            //     return Excel::download(new EmployeeDeductionExport($payroll), "payroll.xls");
            // }

            $payroll = $payroll->paginate($perPage);

            return $this->sendResponse($payroll);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
