<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateThirteenthMonthMonthlyAllocationEntry;
use App\Jobs\GenerateThirteenthMonthPayJob;
use App\Jobs\GenerateThirteenthMonthMonthlyAllocationJob;
use App\Models\BranchesMonthlyAllocation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Exception;
use App\Models\Payroll;
use App\Models\ThirteenthMonthPay;
use App\Models\ThirteenthMonthFiles;
use App\Models\EmployeesMonthlyAllocation;
use DB;

class ReportsThirteenthMonthController extends Controller
{
    public function getAllThirteenthMonth(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $thirteenthMonth = ThirteenthMonthFiles::query()
                ->with("user")
                ->orderBy("year", "DESC")
                ->orderBy("updated_at", "DESC");

            if ($request->has(["type"])) {
                $thirteenthMonth = $thirteenthMonth->where("type", $request->type);
            }

            if ($request->has(["year"])) {
                $thirteenthMonth = $thirteenthMonth->where("year", $request->year);
            }

            if ($request->has(["month"])) {
                $thirteenthMonth = $thirteenthMonth->where("month", $request->month);
            }

            $thirteenthMonth = $thirteenthMonth->paginate($perPage);

            return $this->sendResponse($thirteenthMonth);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getThirteenthMonthPay (Request $request, $id) {
        try {
            $perPage = $request->per_page ?? 10;

            $thirteenthMonthPay = ThirteenthMonthPay::query()
                ->where("thirteenth_month_file_id", $id)
                ->with(["employee"]);
            
            if ($request->has("employee_id") && $request->employee_id) {
                $thirteenthMonthPay = $thirteenthMonthPay->where('employee_id', $request->employee_id);
            } 

            if ($request->has(['branch_id'])) {
                $thirteenthMonthPay = $thirteenthMonthPay->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            $thirteenthMonthPay = $thirteenthMonthPay->paginate($perPage);

            return $this->sendResponse($thirteenthMonthPay);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getEmployeesMonthlyAllocation (Request $request, $id) {
        try {
            $perPage = $request->per_page ?? 10;

            $employeesMonthlyAllocation = EmployeesMonthlyAllocation::query()
                ->where("thirteenth_month_file_id", $id)
                ->with(["employee"]);

            if ($request->has("employee_id") && $request->employee_id) {
                $employeesMonthlyAllocation = $employeesMonthlyAllocation->where('employee_id', $request->employee_id);
            }

            if ($request->has(['branch_id'])) {
                $employeesMonthlyAllocation = $employeesMonthlyAllocation->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            $employeesMonthlyAllocation = $employeesMonthlyAllocation->paginate($perPage);

            return $this->sendResponse($employeesMonthlyAllocation);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function getBranchesMonthlyAllocation (Request $request, $id) {
        try {
            $perPage = $request->per_page ?? 10;

            $branchesMonthlyAllocation = BranchesMonthlyAllocation::query()
                ->where("thirteenth_month_file_id", $id)
                ->with(["branches"]);

            if ($request->has(['branch_id'])) {
                $branchesMonthlyAllocation = $branchesMonthlyAllocation->where("branch_id", $request->branch_id);
            }

            $branchesMonthlyAllocation = $branchesMonthlyAllocation->paginate($perPage);

            return $this->sendResponse($branchesMonthlyAllocation);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function generateThirteenthMonthPay(Request $request)
    {
        try {
            $request->validate([
                "year" => "required",
            ]);

            $user = Auth::user();
            $currentYear = (int) $request->year;
            $previousYear = $currentYear - 1;

            $payrollIds = Payroll::where(function($query) use ($currentYear, $previousYear) {
                $query->whereYear('start_date', $currentYear)
                    ->orWhere(function($q) use ($previousYear) {
                        $q->whereYear('start_date', $previousYear)
                            ->whereMonth('start_date', 12);
                    });
            })
            ->where("is_maternity", 0)
            ->pluck('id')->toArray();

            GenerateThirteenthMonthPayJob::dispatch($user->id, $currentYear, $payrollIds);

            return $this->sendResponse([
                'message' => 'Thirteenth month pay generation started.',
            ]);

        } catch (Exception $e) {
            return $this->sendError([
                'message' => 'Thirteenth month pay generation failed',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function generateThirteenthMonthMonthlyAllocation(Request $request)
    {
        try {
            $request->validate([
                "month" => "required",
                "year" => "required",
            ]);

            $month = (int) $request->month;
            $selectedYear = (int) $request->year;
            $previousYear = $selectedYear - 1;

            $first_cutoff = null;
            $second_cutoff = null;

            if ($month === 1) {
                $first_cutoff  = "{$previousYear}-12-26-{$selectedYear}-01-10";
                $second_cutoff = "{$selectedYear}-01-11-{$selectedYear}-01-25";
            }

            if ($month === 2) {
                $first_cutoff  = "{$selectedYear}-01-26-{$selectedYear}-02-10";
                $second_cutoff = "{$selectedYear}-02-11-{$selectedYear}-02-25";
            }

            if ($month === 3) {
                $first_cutoff  = "{$selectedYear}-02-26-{$selectedYear}-03-10";
                $second_cutoff = "{$selectedYear}-03-11-{$selectedYear}-03-25";
            }

            if ($month === 4) {
                $first_cutoff  = "{$selectedYear}-03-26-{$selectedYear}-04-10";
                $second_cutoff = "{$selectedYear}-04-11-{$selectedYear}-04-25";
            }

            if ($month === 5) {
                $first_cutoff  = "{$selectedYear}-04-26-{$selectedYear}-05-10";
                $second_cutoff = "{$selectedYear}-05-11-{$selectedYear}-05-25";
            }

            if ($month === 6) {
                $first_cutoff  = "{$selectedYear}-05-26-{$selectedYear}-06-10";
                $second_cutoff = "{$selectedYear}-06-11-{$selectedYear}-06-25";
            }

            if ($month === 7) {
                $first_cutoff  = "{$selectedYear}-06-26-{$selectedYear}-07-10";
                $second_cutoff = "{$selectedYear}-07-11-{$selectedYear}-07-25";
            }

            if ($month === 8) {
                $first_cutoff  = "{$selectedYear}-07-26-{$selectedYear}-08-10";
                $second_cutoff = "{$selectedYear}-08-11-{$selectedYear}-08-25";
            }

            if ($month === 9) {
                $first_cutoff  = "{$selectedYear}-08-26-{$selectedYear}-09-10";
                $second_cutoff = "{$selectedYear}-09-11-{$selectedYear}-09-25";
            }

            if ($month === 10) {
                $first_cutoff  = "{$selectedYear}-09-26-{$selectedYear}-10-10";
                $second_cutoff = "{$selectedYear}-10-11-{$selectedYear}-10-25";
            }

            if ($month === 11) {
                $first_cutoff  = "{$selectedYear}-10-26-{$selectedYear}-11-10";
                $second_cutoff = "{$selectedYear}-11-11-{$selectedYear}-11-25";
            }

            if ($month === 12) {
                $first_cutoff  = "{$selectedYear}-11-26-{$selectedYear}-12-10";
                $second_cutoff = "{$selectedYear}-12-11-{$selectedYear}-12-25";
            }

            $user = Auth::user();

            $payrollIds = Payroll::whereIn('cut_off', [$first_cutoff, $second_cutoff])
            ->where("is_maternity", 0)    
            ->pluck('id')
            ->toArray();

            GenerateThirteenthMonthMonthlyAllocationJob::dispatch($user->id, $month, $selectedYear, $payrollIds);

            return $this->sendResponse([
                'message' => 'Thirteenth month monthly allocation generation started.',
            ]);

        } catch (Exception $e) {

            return $this->sendError([
                'message' => 'Thirteenth month monthly allocation generation failed',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function generateThirteenthMonthMonthlyAllocationEntries($id)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();

            $months = [
                1 => 'JANUARY',
                2 => 'FEBRUARY',
                3 => 'MARCH',
                4 => 'APRIL',
                5 => 'MAY',
                6 => 'JUNE',
                7 => 'JULY',
                8 => 'AUGUST',
                9 => 'SEPTEMBER',
                10 => 'OCTOBER',
                11 => 'NOVEMBER',
                12 => 'DECEMBER'
            ];
            
            $monthlyAllocation = ThirteenthMonthFiles::where('id', $id)->first();
            $groupedBranches = BranchesMonthlyAllocation::where('thirteenth_month_file_id', $monthlyAllocation->id)->get();

            $month = $months[$monthlyAllocation->month];
            $year = $monthlyAllocation->year;

            GenerateThirteenthMonthMonthlyAllocationEntry::dispatch($groupedBranches, $user, $month, $year);

            DB::commit();
            return $this->sendResponse(['message' => 'Generation started']);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function downloadExcelFile($filename)
    {
        try {
            $storage = Storage::disk("hr_system_folder");
            $directory = "13th Month Files"; 
            $filePathOnDisk = $directory . '/' . $filename;
            
            if (!$storage->exists($filePathOnDisk)) {
                return response()->json(["message" => "File not found"], 404);
            }

            return $storage->download($filePathOnDisk, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (Exception $e) {
            return response()->json(["message" => $e->getMessage()], 500);
        }
    }
}
