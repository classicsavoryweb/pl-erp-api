<?php

namespace App\Http\Controllers;

use App\Models\ActingCapacity;
use App\Models\Branch;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Tardiness;
use Illuminate\Http\Request;
use Exception;
use Carbon\Carbon;
use DB;

class DashboardController extends Controller
{
    public function getSummaryDetails(Request $request) {
        try {
            $branches = Branch::where("status", "active")->count();
            $probationaryEmployees = Employee::where("status", "active")
            ->whereHas("employee_status", function($query) {
                $query->where("description", "Probationary");
            })
            ->count();
            $regularEmployees = Employee::where("status", "active")
            ->whereHas("employee_status", function($query) {
                $query->where("description", "Regular");
            })
            ->count();
            $contractualEmployees = Employee::where("status", "active")
            ->whereHas("employee_status", function($query) {
                $query->where("description", "Contractual");
            })
            ->count();
            $pendingEmployees = Employee::where("status", "active")
            ->whereHas("employee_status", function($query) {
                $query->where("description", "Pending");
            })
            ->count();

            $monthlyHires = Employee::selectRaw('MONTH(date_hired) as month, COUNT(*) as total')
            ->whereYear('date_hired', Carbon::now()->year)
            ->groupBy(DB::raw('MONTH(date_hired)'))
            ->orderBy(DB::raw('MONTH(date_hired)'))
            ->pluck('total', 'month')
            ->mapWithKeys(function ($value, $key) {
                return [(int)$key => $value];
            })
            ->all();
            $allMonths = collect(range(1, 12))->mapWithKeys(function ($month) use ($monthlyHires) {
                return [$month => $monthlyHires[$month] ?? 0];
            });

            $monthlyTardy = Tardiness::selectRaw('month, COUNT(*) as total')
            ->where('year', Carbon::now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->where("status", "Approved")
            ->pluck('total', 'month')
            ->mapWithKeys(function ($value, $key) {
                return [(int)$key => $value];
            })
            ->all();
            $allTardy = collect(range(1, 12))->mapWithKeys(function ($month) use ($monthlyTardy) {
                return [$month => $monthlyTardy[$month] ?? 0];
            });

            $monthlyPromotions = ActingCapacity::selectRaw('MONTH(confirmed_date) as month, COUNT(*) as total')
            ->whereYear('confirmed_date', Carbon::now()->year)
            ->groupBy(DB::raw('MONTH(confirmed_date)'))
            ->orderBy(DB::raw('MONTH(confirmed_date)'))
            ->where("status", "Confirmed")
            ->pluck('total', 'month')
            ->mapWithKeys(function ($value, $key) {
                return [(int)$key => $value];
            })
            ->all();
            $allPromotions = collect(range(1, 12))->mapWithKeys(function ($month) use ($monthlyPromotions) {
                return [$month => $monthlyPromotions[$month] ?? 0];
            });

            return $this->sendResponse([
                "branches" => $branches,
                "probationary_employees" => $probationaryEmployees,
                "pending_employees" => $pendingEmployees,
                "regular_employees" => $regularEmployees,
                "contractual_employees" => $contractualEmployees,
                "monthly_hires" => $allMonths,
                "monthly_tardy" => $allTardy,
                "monthly_promotions" => $allPromotions,
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getAllEmployeeBirthMonth (Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $startOfWeek = Carbon::now();
            $endOfWeek = Carbon::now()->addDays(5);

            $weekDates = [];
            $cursor = $startOfWeek->copy();
            while ($cursor->lte($endOfWeek)) {
                $weekDates[] = $cursor->format('m-d');
                $cursor->addDay();
            }
            $weekDatesString = "'" . implode("','", $weekDates) . "'";

            $employees = Employee::with(["current_position", "current_branch"])
            ->whereIn(DB::raw("DATE_FORMAT(birthday, '%m-%d')"), $weekDates)
            ->where("status", "active")
            ->orderByRaw("FIELD(DATE_FORMAT(birthday, '%m-%d'), $weekDatesString)")
            ->paginate($perPage);

            return $this->sendResponse($employees);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getAllEmployeeExpiringContracts(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $startingDay = Carbon::now()->subDays(7);
            $endingDay = Carbon::now()->addDays(19);

            // $employees = Employee::with([
            //     'current_position',
            //     'current_branch',
            //     'latest_contract'
            // ])->whereHas('latest_contract', function ($query) use($startingDay, $endingDay) {
            //     $query->where("status", "active");
            // })->where("status", "active")
            // ->orderBy("date_hired", "ASC")
            // ->paginate($perPage);
            $employees = Contract::with([
                "employee",
                "employee.current_position",
                "employee.current_branch"
            ])
            ->whereHas("employee", function ($query) {
                $query->where("status", "active");
            })
            ->where("status", "active")
            ->orderBy("contract_end_date", "ASC")
            ->paginate($perPage);

            return $this->sendResponse($employees);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
