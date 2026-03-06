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

            if ($request->has(["status"])) {
                $coeRequests = $coeRequests->where("status", $request->status);
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
                "basic_salary" => $employee->monthlySalary(),
                "employee_id" => $employee->id,
                "position" => $employee->current_position->description,
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
            $employee = Employee::where("employee_number", $request->employee_number)->first();
            CoeRequest::where("id", $id)
            ->update([
                ...$request->except(["employee", "employee_number"]),
                "position" => $employee->current_position->description,
                "basic_salary" => $employee->monthlySalary(),
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

            $middleInitial = "";
            if (!empty($employee->middle_name)) {
                $middleInitial = strtoupper(substr($employee->middle_name, 0, 1)) . ". ";
            }

            return $this->sendResponse([
                "first_name" => mb_convert_case($employee->first_name, MB_CASE_TITLE, "UTF-8"), 
                "last_name" =>  mb_convert_case($employee->last_name, MB_CASE_TITLE, "UTF-8"),
                "middle_initial" => mb_convert_case($middleInitial, MB_CASE_TITLE, "UTF-8"),
                "gender" => $employee->gender,
                "date_of_stay" => $dateOfStay,
                "include_salary" => $coeRequest->include_salary,
                "salary" => $this->convertToChequeWords($coeRequest->basic_salary),
                "current_position" => $coeRequest->position,
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
