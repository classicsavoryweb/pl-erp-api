<?php

namespace App\Http\Controllers\Transactions;

use App\Exports\Transactions\ScheduleTimeExport;
use App\Http\Controllers\Controller;
use App\Http\Services\TimeKeepingService;
use App\Jobs\ProcessScheduleTimeImport;
use App\Models\ScheduleTime;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use DB;
use Exception;
use Str;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;

class ScheduleTimeController extends Controller
{
    protected $timeKeepingService;

    public function __construct() {
        $this->timeKeepingService = new TimeKeepingService();
    }

    public function getAllTransactionsScheduleTime(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $schedule_time = ScheduleTime::query();
            
            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $schedule_time = $schedule_time->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $schedule_time = $schedule_time->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $schedule_time = $schedule_time->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(['branch_id'])) {
                $schedule_time = $schedule_time->where("branch_id", $request->branch_id);
            }

            if ($request->has('cutoff') && $request->filled('cutoff.start_time') && $request->filled('cutoff.end_time')) {
                $year = $request->year ?? Carbon::now()->year;

                $start_time = $year . '-' . $request->cutoff['start_time'];
                $end_time = $year . '-' . $request->cutoff['end_time'];

                $schedule_time = $schedule_time->whereBetween("date", [$start_time, $end_time]);
            }

            if ($request->has(["export"]) && $request->export) {
                $schedule_time = $schedule_time->get();
                return Excel::download(new ScheduleTimeExport($schedule_time), "schedule_time.xls");
            }

            $schedule_time = $schedule_time
            ->with(["employee", "employee.current_branch", "branch"])
            ->paginate($perPage);

            return $this->sendResponse($schedule_time);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllTransactionsScheduleTime(Request $request) {
        $request["export"] = true;

        return $this->getAllTransactionsScheduleTime($request);
    }

    public function getTransactionsScheduleTime(Request $request) {
        try {
            $schedule_time = ScheduleTime::all();
            return $this->sendResponse(["schedule_time" => $schedule_time]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    private function processExcelRows(
        array $dataRows,
        array $headerRow,
        array &$employeeDetails,
        array &$restDayDatesByEmployee,
        array &$incentiveLeaveDatesByEmployee,
        array &$restDayCounts,
        array &$incentiveLeaveCounts,
        array &$restDaysPerWeek
    ): void {
        foreach ($dataRows as $index => $row) {
            $rowNumber = $index + 3;
            $rowAssoc  = array_combine($headerRow, $row);

            $employeeId     = $rowAssoc['employee_id'] ?? null;
            $dateRaw        = $rowAssoc['date'] ?? null;
            $converted_date = $this->excelDateToDateString($dateRaw);
            $timetable      = $rowAssoc['timetable'] ?? null;
            $leaveType      = strtoupper(trim($timetable));

            $employeeDetails[$employeeId] = $employeeDetails[$employeeId] ?? [
                'employee_id'            => $employeeId,
                'rest_day_count'         => 0,
                'incentive_leave_count'  => 0,
            ];

            if ($leaveType === 'REST DAY') {
                $formattedDate = date('n/j', strtotime($converted_date));

                if (!in_array($formattedDate, $restDayDatesByEmployee[$employeeId] ?? [])) {
                    $restDayDatesByEmployee[$employeeId][] = $formattedDate;

                    $restDayCounts[$employeeId] = ($restDayCounts[$employeeId] ?? 0) + 1;
                    $employeeDetails[$employeeId]['rest_day_count'] = $restDayCounts[$employeeId];
                }

                $week = date('o-W', strtotime($converted_date));
                if (!in_array($converted_date, $restDaysPerWeek[$employeeId][$week] ?? [])) {
                    $restDaysPerWeek[$employeeId][$week][] = $converted_date;
                }
            }

            if ($leaveType === 'INCENTIVE LEAVE') {
                $formattedDate = date('n/j', strtotime($converted_date));

                if (!in_array($formattedDate, $incentiveLeaveDatesByEmployee[$employeeId] ?? [])) {
                    $incentiveLeaveDatesByEmployee[$employeeId][] = $formattedDate;

                    $incentiveLeaveCounts[$employeeId] = ($incentiveLeaveCounts[$employeeId] ?? 0) + 1;
                    $employeeDetails[$employeeId]['incentive_leave_count'] = $incentiveLeaveCounts[$employeeId];
                }
            }
        }
    }

    private function mergePriorLeavesWithExcelData(
        array $dataRows,
        array $previousCutoff,
        array $currentCutoff,
        array &$employeeDetails,
        array &$restDayDatesByEmployee,
        array &$incentiveLeaveDatesByEmployee,
        array &$restDayCounts,
        array &$incentiveLeaveCounts
    ): void {
        $excelEmployeeIds = collect($dataRows)->pluck(0)->filter()->unique()->toArray();

        $priorLeaves = ScheduleTime::whereBetween('date', [$previousCutoff['start_time'], $currentCutoff['end_time']])
            ->whereIn('timetable', ['REST DAY', 'INCENTIVE LEAVE'])
            ->whereIn('biometric_number', $excelEmployeeIds)
            ->get()
            ->groupBy('biometric_number');

        foreach ($priorLeaves as $employeeId => $entries) {
            foreach ($entries as $entry) {
                $leaveType = strtoupper(trim($entry->timetable));

                $employeeDetails[$employeeId] = $employeeDetails[$employeeId] ?? [
                    'employee_id'          => $employeeId,
                    'rest_day_count'       => 0,
                    'incentive_leave_count'=> 0,
                ];

                if ($leaveType === 'REST DAY') {
                    $formattedDate = date('n/j', strtotime($entry->date));

                    if (!in_array($formattedDate, $restDayDatesByEmployee[$employeeId] ?? [])) {
                        $restDayDatesByEmployee[$employeeId][] = $formattedDate;

                        $restDayCounts[$employeeId] = ($restDayCounts[$employeeId] ?? 0) + 1;
                        $employeeDetails[$employeeId]['rest_day_count'] = $restDayCounts[$employeeId];
                    }
                } elseif ($leaveType === 'INCENTIVE LEAVE') {
                    $formattedDate = date('n/j', strtotime($entry->date));

                    if (!in_array($formattedDate, $incentiveLeaveDatesByEmployee[$employeeId] ?? [])) {
                        $incentiveLeaveDatesByEmployee[$employeeId][] = $formattedDate;

                        $incentiveLeaveCounts[$employeeId] = ($incentiveLeaveCounts[$employeeId] ?? 0) + 1;
                        $employeeDetails[$employeeId]['incentive_leave_count'] = $incentiveLeaveCounts[$employeeId];
                    }
                }
            }
        }
    }

    private function getEmployeesWithRestDayInBothCutoffs(
        array $restDayDatesByEmployee,
        array $previousCutoff,
        array $currentCutoff
    ): array {
        $employeesWithRestDayInBothCutoffs = [];

        foreach ($restDayDatesByEmployee as $employeeId => $dates) {
            $duplicatedRestDayDates = [];

            foreach ($dates as $date) {
                $carbonDate = Carbon::createFromFormat('n/j', $date)->setYear(now()->year);
                $dateRange  = $this->timeKeepingService->getWeekRange($carbonDate);

                $hasAnotherRestDay = ScheduleTime::whereBetween("date", [$dateRange['start'], $dateRange['end']])
                    ->where("biometric_number", $employeeId)
                    ->where("timetable", "REST DAY")
                    ->whereDate("date", '!=', $carbonDate->toDateString()) 
                    ->first();

                if ($hasAnotherRestDay) {
                    $date1 = Carbon::parse($hasAnotherRestDay->date);
                    $date2 = $carbonDate;

                    $inPreviousCutoff = $date1->betweenIncluded($previousCutoff['start_time'], $previousCutoff['end_time']) ||
                        $date2->betweenIncluded($previousCutoff['start_time'], $previousCutoff['end_time']);

                    $inCurrentCutoff = $date1->betweenIncluded($currentCutoff['start_time'], $currentCutoff['end_time']) ||
                        $date2->betweenIncluded($currentCutoff['start_time'], $currentCutoff['end_time']);

                    if ($inPreviousCutoff && $inCurrentCutoff) {
                        $duplicatedRestDayDates[$employeeId][] = $date1->format('n/j');
                        $duplicatedRestDayDates[$employeeId][] = $date2->format('n/j');
                    }
                }
            }

            if (!empty($duplicatedRestDayDates[$employeeId])) {
                $employee = Employee::where('biometric_number', $employeeId)->first();

                if ($employee) {
                    $employeesWithRestDayInBothCutoffs[] = [
                        'employee_id'    => $employeeId,
                        'employee_name'  => $employee->full_name,
                        'rest_day_dates' => array_values(array_unique($duplicatedRestDayDates[$employeeId])),
                    ];
                }
            }
        }
        return $employeesWithRestDayInBothCutoffs;
    }

    private function getEmployeesWithMultipleRestDaysPerWeek(array $restDaysPerWeek): array
    {
        $employeesWithMultipleRestDays = [];

        foreach ($restDaysPerWeek as $employeeId => $weeks) {
            foreach ($weeks as $week => $dates) {
                if (count($dates) > 1) {
                    $employee = Employee::where('biometric_number', $employeeId)->first();

                    if ($employee) {
                        $formattedDates = array_map(function ($d) {
                            $ts = strtotime($d);
                            return $ts ? date('n/j', $ts) : $d;
                        }, $dates);

                        $employeesWithMultipleRestDays[] = [
                            'employee_id'    => $employeeId,
                            'employee_name'  => $employee->full_name,
                            'rest_day_dates' => $formattedDates,
                        ];
                    }
                }
            }
        }
        return $employeesWithMultipleRestDays;
    }

    private function getEmployeesWithExcessiveTimetableValues(
        array $employeeDetails,
        array $restDayDatesByEmployee,
        array $incentiveLeaveDatesByEmployee
    ): array {
        $employeesWithExcessiveTimetableValues = [];

        foreach ($employeeDetails as $employeeId => $details) {
            if ($details['rest_day_count'] >= 3 || $details['incentive_leave_count'] >= 3) {
                $employee = Employee::where('biometric_number', $employeeId)->first();

                if ($employee) {
                    $employeesWithExcessiveTimetableValues[] = [
                        'employee_id'             => $employeeId,
                        'employee_name'           => $employee->full_name,
                        'rest_day_count'          => $details['rest_day_count'],
                        'incentive_leave_count'   => $details['incentive_leave_count'],
                        'rest_day_dates'          => $restDayDatesByEmployee[$employeeId] ?? [],
                        'incentive_leave_dates'   => $incentiveLeaveDatesByEmployee[$employeeId] ?? [],
                    ];
                }
            }
        }

        return $employeesWithExcessiveTimetableValues;
    }

    public function validateImportTransactionsScheduleTimeExcel(Request $request)
    {
        try {
            $request->validate([
                "schedule_time_file" => "required"
            ]);

            if ($request->hasFile("schedule_time_file")) {
                $storage = Storage::disk("hr_system_folder");
                $directory = "/imports";
                $file = $request->file("schedule_time_file");

                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }

                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));

                $restDayCounts = [];
                $incentiveLeaveCounts = [];
                $employeeDetails = [];
                $restDaysPerWeek = [];

                $restDayDatesByEmployee = [];
                $incentiveLeaveDatesByEmployee = [];

                $dataRows = Excel::toArray([], $file);

                $rawHeaderRow = $dataRows[0][1]; 
                $dataRows     = array_slice($dataRows[0], 2); 

                $headerRow = array_map(function ($header) {
                    return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
                }, $rawHeaderRow);

                $currentCutoff = $this->getCurrentCutOff();
                $previousCutoff = $this->getPreviousCutOff($currentCutoff['start_time']);

                $datesByEmployee = [];

                foreach ($dataRows as $index => $row) {
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $rowNumber   = $index + 3;
                    $rowAssoc = array_combine($headerRow, $row);

                    $employeeId = trim((string) ($rowAssoc['employee_id'] ?? ''));
                    $department = $rowAssoc['department'] ?? null;
                    $dateRaw    = $rowAssoc['date'] ?? null;
                    $timetable  = $rowAssoc['timetable'] ?? null;
                    $checkIn    = $rowAssoc['check_in'] ?? null;
                    $checkOut   = $rowAssoc['check_out'] ?? null;

                    $employee = Employee::where('biometric_number', $employeeId)->first();
                    if (!$employee) {
                        return $this->sendError([
                            "message" => "Employee ID {$employeeId} not found at row {$rowNumber}"
                        ]);
                    }

                    $requiredFields = [
                        'employeeId' => "Employee ID",
                        'dateRaw'    => "Date",
                        'department' => "Department",
                        'timetable'  => "Timetable",
                        'checkIn'    => "Check-in",
                        'checkOut'   => "Check-out",
                    ];

                    foreach ($requiredFields as $field => $label) {
                        if (empty($$field)) {
                            return $this->sendError([
                                "message" => "{$label} value is missing at row {$rowNumber}"
                            ]);
                        }
                    }

                    if ($employeeId && $dateRaw) {
                        $convertedDate = $this->excelDateToDateString($dateRaw);
                        $datesByEmployee[$employeeId][] = $convertedDate;
                    }
                }

                foreach ($datesByEmployee as $empId => $employeeDates) {
                    $firstDate = reset($employeeDates);
                    $cutoff    = $this->getCutOffForDate($firstDate);

                    $cutoffPeriod  = CarbonPeriod::create($cutoff['start_time'], $cutoff['end_time']);
                    $expectedDates = collect($cutoffPeriod)->map->toDateString()->toArray();

                    $missing = array_diff($expectedDates, $employeeDates);
                    if (count($missing) > 0) {
                        return $this->sendError([
                            "message" => "Employee with ID {$employeeId} has incomplete schedule."
                        ]);
                    }
                }

                $this->processExcelRows(
                    $dataRows,
                    $headerRow,
                    $employeeDetails,
                    $restDayDatesByEmployee,
                    $incentiveLeaveDatesByEmployee,
                    $restDayCounts,
                    $incentiveLeaveCounts,
                    $restDaysPerWeek
                );
                
                $this->mergePriorLeavesWithExcelData(
                    $dataRows,
                    $previousCutoff,
                    $currentCutoff,
                    $employeeDetails,
                    $restDayDatesByEmployee,
                    $incentiveLeaveDatesByEmployee,
                    $restDayCounts,
                    $incentiveLeaveCounts
                );

                $employeesWithRestDayInBothCutoffs = $this->getEmployeesWithRestDayInBothCutoffs(
                    $restDayDatesByEmployee,
                    $previousCutoff,
                    $currentCutoff
                );

                $employeesWithMultipleRestDays = $this->getEmployeesWithMultipleRestDaysPerWeek(
                    $restDaysPerWeek
                );

                $employeesWithExcessiveTimetableValues = $this->getEmployeesWithExcessiveTimetableValues(
                    $employeeDetails,
                    $restDayDatesByEmployee,
                    $incentiveLeaveDatesByEmployee
                );
            }
            
            return $this->sendResponse([
                "file_path" => $filePath,
                "employees_with_excessive_timetable_values" => $employeesWithExcessiveTimetableValues,
                "employees_with_rest_day_in_both_cutoffs" => $employeesWithRestDayInBothCutoffs,
                "employees_with_multiple_rest_days_per_week" => $employeesWithMultipleRestDays,
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function importTransactionsScheduleTimeExcel(Request $request) {
        try {
            $request->validate([
                "schedule_time_file" => "required_without:file_path|file",  
                "file_path" => "required_without:schedule_time_file|string",
                "excluded_employee_ids" => "array"
            ]);

            $filePath = null;
            $excludedEmployeeIds = $request->input('excluded_employee_ids', []);

            if ($request->hasFile("schedule_time_file")) {
                $storage = Storage::disk("hr_system_folder");
                $directory = "/imports";
                $file = $request->file("schedule_time_file");

                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }

                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));

            } elseif ($request->has("file_path")) {
                $filePath = $request->file_path;
            }

            if ($filePath) {
                ProcessScheduleTimeImport::dispatch($filePath, $excludedEmployeeIds);
                $user = Auth::user();
                activity()->causedBy($user)->useLog("Import Schedule")->log('User imported schedule');
            }

            return $this->sendResponse(["message" => "Excel file imported successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
