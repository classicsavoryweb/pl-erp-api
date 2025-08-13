<?php

namespace App\Http\Controllers\Transactions;

use App\Exports\Transactions\ScheduleTimeExport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessScheduleTimeImport;
use App\Models\ScheduleTime;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use DB;
use Exception;
use Str;
use Maatwebsite\Excel\Facades\Excel;

class ScheduleTimeController extends Controller
{
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

            if ($request->has(["date_from", "date_to"])) {
                $schedule_time = $schedule_time->whereBetween('date', [$request->date_from, $request->date_to]);
            }

            if ($request->has(["export"]) && $request->export) {
                $schedule_time = $schedule_time->get();
                return Excel::download(new ScheduleTimeExport($schedule_time), "schedule_time.xls");
            }

            if ($request->has(['branch_id'])) {
                $schedule_time = $schedule_time->where("branch_id", $request->branch_id);
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

                $leaveCounts = [
                    'REST DAY' => &$restDayCounts,
                    'INCENTIVE LEAVE' => &$incentiveLeaveCounts
                ];

                $restDayDatesByEmployee = [];
                $incentiveLeaveDatesByEmployee = [];

                $currentCutoff = $this->getCutOffForDate(now());
                
                if ($currentCutoff['start_time'] === now()->copy()->startOfMonth()->addDays(10)->toDateString()) {
                    $previousCutoff = $this->getPreviousCutOff(now());

                    $priorLeaves = ScheduleTime::whereBetween('date', [$previousCutoff['start_time'], $previousCutoff['end_time']])
                        ->whereIn('timetable', ['REST DAY', 'INCENTIVE LEAVE'])
                        ->get()
                        ->groupBy('biometric_number');

                    foreach ($priorLeaves as $employeeId => $entries) {
                        foreach ($entries as $entry) {
                            $leaveType = strtoupper(trim($entry->timetable));

                            $employeeDetails[$employeeId] = [
                                'employee_id' => $employeeId,
                                'rest_day_count' => 0,
                                'incentive_leave_count' => 0,
                            ];

                            if ($leaveType === 'REST DAY') {
                                $restDayCounts[$employeeId] = ($restDayCounts[$employeeId] ?? 0) + 1;
                                $employeeDetails[$employeeId]['rest_day_count'] = $restDayCounts[$employeeId];
                                $restDayDatesByEmployee[$employeeId][] = date('n/j', strtotime($entry->date));
                            } elseif ($leaveType === 'INCENTIVE LEAVE') {
                                $incentiveLeaveCounts[$employeeId] = ($incentiveLeaveCounts[$employeeId] ?? 0) + 1;
                                $employeeDetails[$employeeId]['incentive_leave_count'] = $incentiveLeaveCounts[$employeeId];
                                $incentiveLeaveDatesByEmployee[$employeeId][] = date('n/j', strtotime($entry->date));
                            }
                        }
                    }
                }

                $dataRows = Excel::toArray([], $file);
                $dataRows = array_slice($dataRows[0], 2); 

                foreach ($dataRows as $index => $row) {
                    $employeeId = $row[0] ?? null;
                    $dateRaw = $row[4] ?? null;
                    $date = $this->excelDateToDateString($dateRaw);
                    $timetable  = $row[5] ?? null;
                    $leaveType = strtoupper(trim($timetable));
                    if ($employeeId && $date && $leaveType === 'REST DAY') {
                        $restDayDatesByEmployee[$employeeId][] = date('n/j', strtotime($date));
                    }
                    if ($employeeId && $date && $leaveType === 'INCENTIVE LEAVE') {
                        $incentiveLeaveDatesByEmployee[$employeeId][] = date('n/j', strtotime($date));
                    }
                }

                foreach ($dataRows as $index => $row) {
                    $employeeId = $row[0] ?? null;
                    $dateRaw = $row[4] ?? null;
                    $date = $this->excelDateToDateString($dateRaw);
                    $timetable  = $row[5] ?? null;

                    if (strtoupper(trim($timetable)) === 'REST DAY' && $employeeId && $date) {
                        $week = date('o-W', strtotime($date));
                        $restDaysPerWeek[$employeeId][$week][] = $date;
                    }

                    if (isset($leaveCounts[strtoupper(trim($timetable))])) {
                        $leaveType = strtoupper(trim($timetable));
                        $leaveCounts[$leaveType][$employeeId] = ($leaveCounts[$leaveType][$employeeId] ?? 0) + 1;

                        $employeeDetails[$employeeId] = [
                            'employee_id' => $employeeId,
                            'rest_day_count' => 0,
                            'incentive_leave_count' => 0,
                        ];

                        if ($leaveType === 'REST DAY') {
                            $employeeDetails[$employeeId]['rest_day_count'] = $leaveCounts[$leaveType][$employeeId];
                        } elseif ($leaveType === 'INCENTIVE LEAVE') {
                            $employeeDetails[$employeeId]['incentive_leave_count'] = $leaveCounts[$leaveType][$employeeId];
                        }
                    }
                }

                $employeesWithMultipleRestDays = [];

                foreach ($restDaysPerWeek as $employeeId => $weeks) {
                    foreach ($weeks as $week => $dates) {
                        if (count($dates) > 1) {
                            $employee = Employee::where('biometric_number', $employeeId)->first();
                            $employeeName = $employee->full_name;
                            $formattedDates = array_map(function($d) {
                                $ts = strtotime($d);
                                return $ts ? date('n/j', $ts) : $d;
                            }, $dates);
                            $employeesWithMultipleRestDays[] = [
                                'employee_id' => $employeeId,
                                'employee_name' => $employeeName,
                                'rest_day_dates' => $formattedDates,
                            ];
                        }
                    }
                }

                $employeesWithExcessiveLeaves = [];
                
                foreach ($employeeDetails as $employeeId => $details) {
                    if ($details['rest_day_count'] >= 3 || $details['incentive_leave_count'] >= 3) {
                        $employee = Employee::where('biometric_number', $employeeId)->first();
                        $employeeName = $employee->full_name;
                        $employeesWithExcessiveLeaves[] = [
                            'employee_id' => $employeeId,
                            'employee_name' => $employeeName,
                            'rest_day_count' => $details['rest_day_count'],
                            'incentive_leave_count' => $details['incentive_leave_count'],
                            'rest_day_dates' => $restDayDatesByEmployee[$employeeId] ?? [],
                            'incentive_leave_dates' => $incentiveLeaveDatesByEmployee[$employeeId] ?? [],
                        ];
                    }
                }
            }
            
            return $this->sendResponse([
                "file_path" => $filePath,
                "employees_with_excessive_leaves" => $employeesWithExcessiveLeaves,
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
            }

            return $this->sendResponse(["message" => "Excel file imported successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
