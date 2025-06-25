<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\CoeRequest;
use App\Models\Employee;
use App\Models\Rate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use DB;
use Str;

class ReportCoeRequestsController extends Controller
{
    public function getAllCoeRequests(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $coeRequests = CoeRequest::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $coeRequests = $coeRequests->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $coeRequests = $coeRequests->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $coeRequests = $coeRequests->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["date_from", "date_to"])) {
                $coeRequests = $coeRequests->whereBetween('date_requested', [$request->date_from, $request->date_to]);
            }

            $coeRequests = $coeRequests->with([
                "employee",
                "employee.employee_status"
            ])
            ->orderBy("created_at", "DESC")
            ->paginate($perPage);

            return $this->sendResponse($coeRequests);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createCoeRequest(Request $request) {
        try {
            $request->validate([
                'employee_number' => "required",
                "include_salary" => "required",
                "coe_format" => "required",
                "coe_reason" => "required",
            ]);
            $employee = Employee::where("employee_number", $request->employee_number)->first();
            CoeRequest::create([
                ...$request->except(["employee_number"]),
                "employee_id" => $employee->id,
                "status" => "Pending",
                "date_requested" => now()
            ]);

            return $this->sendResponse(["message" => "COE Request created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editCoeRequest(Request $request, $id) {
        try {
            $request->validate([
                "include_salary" => "required",
                "coe_format" => "required",
                "coe_reason" => "required",
            ]);

            CoeRequest::where("id", $id)
            ->update([
                ...$request->except(["employee", "employee_number"])
            ]);

            return $this->sendResponse(["message" => "COE Request updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getCoeRequest($id ) {
        try {
            $coeRequest = CoeRequest::where("id", $id)->first();
            $employee = $coeRequest->employee()->first();
            $position = $employee->current_position()->first();
            $branch = $employee->home_branch()->first();
            $positions = $employee->positions()->get();

            $positions = $positions->sortBy(function ($position) {
                return $position->pivot->date_started;
            })->values();

            $dateOfStay = "";
            $dateStarted = Carbon::parse($employee->date_hired)->format('F j, Y');
            if ($employee->status === "inactive") {
                $dateSeparated = Carbon::parse($employee->separation_date)->format('F j, Y');
                $dateOfStay = $dateStarted . " to " . $dateSeparated;
            } else {
                $dateOfStay = $dateStarted. " up to present";
            }
            

            $salary_amount = 0.00;
            $salary = $position->salary()->first();

            if ( strtolower($salary->rate_category) !== "minimum") {
                $salary_amount = $salary->basic_salary ?? 0;
            } else {
                $rate = Rate::where("code", $branch->rate_code)->first();
                $salary_amount = $rate->minimum * 26;
            }

            $result = [];

            for ($i = 0; $i < count($positions); $i++) {
                $current = $positions[$i];
                $startDate = Carbon::parse($current->pivot->date_started)->format('F j, Y');
                if (isset($positions[$i + 1])) {
                    $endDate = Carbon::parse($positions[$i + 1]->pivot->date_started)->subDay()->format('F j, Y');
                } else {
                    $endDate = Carbon::parse($employee->separation_date)->format('F j, Y');
                }
                $result[] = [
                    'position'   => $current->description,
                    'date_start' => $startDate,
                    'date_end'   => $endDate,
                ];
            }

            $purpose = $coeRequest->coe_reason;
            if (
                $coeRequest->coe_reason === "Travel Purposes only" ||
                $coeRequest->coe_reason === "Certificate of Leave - Special" ||
                $coeRequest->coe_reason === "Travel Purposes - Special"
            ) {
                $purpose = $coeRequest->places;
            }

            return $this->sendResponse([
                "first_name" => ucwords(strtolower($employee->first_name)),
                "last_name" => ucwords(strtolower($employee->last_name)),
                "middle_name" => ucwords(strtolower($employee->middle_name)),
                "gender" => $employee->gender,
                "date_of_stay" => $dateOfStay,
                "include_salary" => $coeRequest->include_salary,
                "salary" => $this->convertToChequeWords($salary_amount),
                "current_position" => $position->description,
                "purpose" => $purpose,
                "positions" => $result,
                "employee_status" => $employee->status,
                "coe_reason" => $coeRequest->coe_reason,
                "leave_from" => Carbon::parse($coeRequest->leave_from)->format('F j, Y'),
                "leave_to" => Carbon::parse($coeRequest->leave_to)->format('F j, Y'),
                "employment_status" => $employee->employee_status->description
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function cancelCoeRequest($id) {
        try {
            $coeRequest = CoeRequest::where("id", $id)->first();

            if ($coeRequest->status === "Pending") {
                $coeRequest->update([
                    "status" => "Cancelled"
                ]);
                return $this->sendResponse(["message" => "COE Request cancelled successfully"]);
            } else {
                return $this->sendError(["message" => "Cancellation not allowed"]);
            }

        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function approveCoeRequest($id) {
        try {
            $coeRequest = CoeRequest::where("id", $id)->first();

            if ($coeRequest->status === "Pending") {
                $coeRequest->update([
                    "status" => "Approved"
                ]);
                return $this->sendResponse(["message" => "COE Request approved successfully"]);
            } else {
                return $this->sendError(["message" => "Approval not allowed"]);
            }

        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
