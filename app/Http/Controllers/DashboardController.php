<?php

namespace App\Http\Controllers;

use App\Models\ActingCapacity;
use App\Models\Branch;
use App\Models\Contract;
use App\Models\Cutoff;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\PayrollList;
use App\Models\RequestAndIssuance;
use App\Models\RequestAndIssuanceItems;
use App\Models\Tardiness;
use App\Models\UniformFreeUniformLimit;
use App\Models\UniformItem;
use App\Models\UniformReceiving;
use App\Models\UniformReplenishment;
use App\Models\UniformVariance;
use App\Models\UniformVarianceItem;
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

    public function getPayrollSummaryDetails(Request $request) {
        try {
            $activeCutOff = Cutoff::where("status", "active")->first();
            $payrollList = PayrollList::where("is_included", true)->get()->count();
            $deductions = EmployeeDeduction::where("approved_status", "Yes")
            ->whereHas("employee", function ($query) {
                $query->where("type", "Direct");
            })
            ->where("end_loan_status", "No")
            ->where("balance", ">", 0);

            return $this->sendResponse([
                "active_cut_off" => $activeCutOff,
                "payroll_list" => $payrollList,
                "active_deductions" => $deductions->count(),
                "active_deductions_amount" => $deductions->sum("balance")
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getAllEmployeeAnniversary (Request $request) {
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
            ->whereIn(DB::raw("DATE_FORMAT(date_hired, '%m-%d')"), $weekDates)
            ->where("status", "active")
            ->orderByRaw("FIELD(DATE_FORMAT(date_hired, '%m-%d'), $weekDatesString)")
            ->paginate($perPage);

            return $this->sendResponse($employees);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getSummaryUniformDetails(Request $request){
        try{
            $total_request_atd = RequestAndIssuance::whereIn("status", ["Unposted", "Reviewed"])
            ->where("issuance_type","Authority To Deduct")
            ->count();
            $total_request_free = RequestAndIssuance::whereIn("status",["Unposted","Reviewed"])
            ->where("issuance_type","Free Uniform")
            ->count();
            $total_replenishment = UniformReplenishment::whereIn("status", ["Unposted", "Reviewed"])
                ->count();
            $total_partially_received = UniformReplenishment::where("status" ,"Partially Received")
                ->count();
            $total_receiving = UniformReceiving::whereIn("status",["Unposted","Reviewed"])
                ->count();
            $items_need_replenish = UniformItem::where("status","active")
                ->whereColumn("stock_on_hand","<=","crit_level")
                ->count();
            $total_variance_cost = UniformVarianceItem::whereHas('varianceHeader', function ($query) {
                $query->where('status', 'Posted ');
            })->whereHas('varianceHeader', function ($query) {
                $query->whereIn('id', [
                    UniformVariance::where('status', 'Posted')
                        ->latest('id')
                        ->value('id')
                ]);
            })->sum('total_variance_cost');

            $total_atd = RequestAndIssuance::selectRaw('MONTH(date_posted) as month,COUNT(*) as total')
                ->where("status", "Posted")
                ->where("issuance_type", "Authority To Deduct")
                ->whereYear('date_posted', Carbon::now()->year)
                ->groupBy(DB::raw('MONTH(date_posted)'))
                ->orderBy(DB::raw('MONTH(date_posted)'))
                ->pluck('total','month')
                ->mapWithKeys(function ($value,$key){
                    return [(int)$key => $value];
                })
                ->all();
                $all_atd = collect(range(1,12))-> mapWithKeys(function ($month)
                use ($total_atd){
                    return [$month => $total_atd[$month] ?? 0];
                });
            
            $total_free = RequestAndIssuance::selectRaw('MONTH(date_posted) as month,COUNT(*) as total')
                ->where("status","Posted")
                ->where("issuance_type", "Free Uniform")
                ->whereYear('date_posted',Carbon::now()->year)
                ->groupBy(DB::raw('MONTH(date_posted)'))
                ->orderBy(DB::raw('MONTH(date_posted)'))
                ->pluck('total','month')
                ->mapWithKeys(function ($value,$key){
                    return [(int)$key => $value];
                })
                ->all();
                $all_free = collect(range(1,12))-> mapWithKeys(function ($month)
                use ($total_free){
                    return [$month => $total_free[$month] ?? 0];
                });
            
            $total_variance = UniformVariance::selectRaw('
                MONTH(uniform_variances.posted_at) as month,

                SUM(
                    CASE
                        WHEN uniform_variance_items.total_variance_cost > 0
                        THEN uniform_variance_items.total_variance_cost
                        ELSE 0
                    END
                ) as positive,

                SUM(
                    CASE
                        WHEN uniform_variance_items.total_variance_cost < 0
                        THEN uniform_variance_items.total_variance_cost
                        ELSE 0
                    END
                ) as negative
            ')
                ->join(
                    'uniform_variance_items',
                    'uniform_variances.id',
                    '=',
                    'uniform_variance_items.variance_id'
                )
                ->where('uniform_variances.status', 'Posted')
                ->whereYear('uniform_variances.posted_at', Carbon::now()->year)
                ->groupBy(DB::raw('MONTH(uniform_variances.posted_at)'))
                ->orderBy(DB::raw('MONTH(uniform_variances.posted_at)'))
                ->get()
                ->keyBy('month');

            $all_variance = collect(range(1, 12))->mapWithKeys(function ($month) use ($total_variance) {
                $variance = $total_variance->get($month);

                return [
                    $month => [
                        'positive' => $variance ? (float) $variance->positive : 0,
                        'negative' => $variance ? (float) $variance->negative : 0,
                    ],
                ];
            });

           $most_requested_items = RequestAndIssuanceItems::selectRaw('
            uniform_item_no,
                description,
                SUM(requested_quantity) as total_requested
            ')
            ->whereHas('issuance', function ($query) {
                $query->where('status', 'Posted')
                    ->where('date_requested', '>=', Carbon::now()->subDays(60));
            })
            ->groupBy('uniform_item_no', 'description')
            ->orderByDesc('total_requested')
            ->limit(10)
            ->get();

            return $this-> sendResponse([
                "total_replenishment" => $total_replenishment,
                "total_partially_received" => $total_partially_received,
                "total_receiving" => $total_receiving,
                "total_atd" => $all_atd,
                "total_free" => $all_free,
                "total_variance" => $all_variance,
                "total_variance_cost" => $total_variance_cost,
                "most_requested_items" => $most_requested_items,
                "total_request_atd" => $total_request_atd,
                "total_request_free" => $total_request_free,
                "item_need_replenish" => $items_need_replenish,
            ]);

        }catch(Exception $e){
            return $this->sendError(["message" => $e->getMessage()]);
        }
        
    }

    public function getNearlyOufofStockItem(Request $request){
        try {
            $perPage = $request->per_page ?? 10;
            $item_out_of_stock = UniformItem::where("status","active")
                ->whereColumn("stock_on_hand", "<=", "crit_level")
                ->orderBy("stock_on_hand")
                ->paginate($perPage);
            return $this->sendResponse($item_out_of_stock);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    
    }

}
