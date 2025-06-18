<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\EmployeeOffensesExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeOffense;
use Exception;
use DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeOffensesControlller extends Controller
{
    public function getEmployeeOffenses (Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $storage = Storage::disk("hr_system_folder");

            $employee = Employee::where("employee_number", $employee_number)->first();
            $offenses = EmployeeOffense::where("employee_id", $employee->id)
                ->with(["offense", "sanction", "employee"])
                ->orderBy("date_committed","DESC");

            if($request->has(["export"]) && $request->export) {
                $offenses = $offenses->get();
                return Excel::download(new EmployeeOffensesExport($offenses), "offenses.xls");
            }

            $offenses = $offenses->paginate($perPage);

            $offenses->getCollection()->transform(function ($offense) use ($storage) {
                if ($offense->attachment && $storage->exists($offense->attachment)) {

                    $mimeType = $storage->mimeType($offense->attachment);
                    // $actingCapacity->recommendation_letter ='data:'. $mimeType .';base64,' . base64_encode($fileContents);
                    $fileContents = $storage->get($offense->attachment);
                    $offense->attached_file ='data:'.$mimeType.';base64,' . base64_encode($fileContents);
                } else {
                    $offense->attached_file = null;
                }

                return $offense;
            });

            return $this->sendResponse($offenses);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportEmployeeOffenses(Request $request, $employee_number) {
        $request["export"] = true;

        return $this->getEmployeeOffenses($request, $employee_number);
    }

    public function addOffense (Request $request, $employee_number) {
        try {
            DB::beginTransaction();
            $request->validate([     
                'offense_id' => "required",
                'sanction_id' => "required",
                'transgression' => "required",
                'repetition' => "required",
                'summary' => "required",
                'date_committed' => "required",
                'offense_action' => "required",
                'date_served_ao' => "required",
                'suspension_dates' => "required",
                'date_served_da' => "required",
                'remarks' => "required",
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            $employee->offenses()->create([
                ...$request->all()
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Offense added successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editOffense (Request $request, $employee_number) {
        try {
            DB::beginTransaction();
            $request->validate([   
                "id" => "required",     
                'offense_id' => "required",
                'sanction_id' => "required",
                'transgression' => "required",
                'repetition' => "required",
                'summary' => "required",
                'date_committed' => "required",
                'offense_action' => "required",
                'date_served_ao' => "required",
                'suspension_dates' => "required",
                'date_served_da' => "required",
                'remarks' => "required",
            ]);

            $employee = Employee::where("employee_number", $employee_number)->first();
            $pathName = "";
            $originalFileName = "";
            if ($request->hasFile("attachment")) {
                $file = $request->file("attachment");
                $storage = Storage::disk("hr_system_folder");
                $directory = "/Files Per Employee/" . $employee->employee_number;
                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }
                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));
                $pathName = $filePath;
                $originalFileName = $file->getClientOriginalName();
            } else {
                $pathName = $request->attachment;
                $originalFileName = $request->attachment_file_name;
            }
            $employee->offenses()->where("id", $request->id)->update([
                "attachment" => $pathName,
                "attachment_file_name" => $originalFileName,
                ...$request->except("attachment", "attachment_file_name")
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Offense updated successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
