<?php

namespace App\Http\Controllers;

use App\Models\Branch;
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

            $monthlyHires = Employee::selectRaw('MONTH(date_hired) as month, COUNT(*) as total')
                ->whereYear('date_hired', Carbon::now()->year)
                ->groupBy(DB::raw('MONTH(date_hired)'))
                ->orderBy(DB::raw('MONTH(date_hired)'))
                ->pluck('total', 'month')
                ->all();
            $allMonths = collect(range(1, 12))->mapWithKeys(function ($month) use ($monthlyHires) {
                return [$month => $monthlyHires[$month] ?? 0];
            });

            $monthlyTardy = Tardiness::selectRaw('month, COUNT(*) as total')
            ->where('year', Carbon::now()->year)  // <- simple where here
            ->groupBy('month')
            ->orderBy('month')
            ->where("status", "Approved")
            ->pluck('total', 'month')
            ->all();

            $allTardy = collect(range(1, 12))->mapWithKeys(function ($month) use ($monthlyTardy) {
                return [$month => $monthlyTardy[$month] ?? 0];
            });

            return $this->sendResponse([
                "branches" => $branches,
                "probationary_employees" => $probationaryEmployees,
                "regular_employees" => $regularEmployees,
                "contractual_employees" => $contractualEmployees,
                "monthly_hires" => $allMonths,
                "monthly_tardy" => $allTardy,
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getAllEmployeeBirthMonth (Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $startOfWeek = Carbon::now()->startOfWeek();
            $endOfWeek = Carbon::now()->endOfWeek();

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
}
