<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\EmployeeOffensesExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmployeeOffense;
use App\Models\OffensesAttachment;
use Exception;
use DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class EmployeeOffensesControlller extends Controller
{
    public function getEmployeeOffenses (Request $request, $employee_number) {
        try {
            $perPage = $request->per_page ??10;
            $storage = Storage::disk("hr_system_folder");

            $employee = Employee::where("employee_number", $employee_number)->first();
            $offenses = EmployeeOffense::with('attachments')
                ->where("employee_id", $employee->id)
                ->with(["offense", "sanction", "employee"])
                ->orderBy("date_committed","DESC");

            if($request->has(["export"]) && $request->export) {
                $offenses = $offenses->get();
                return Excel::download(new EmployeeOffensesExport($offenses), "offenses.xls");
            }

            $offenses = $offenses->paginate($perPage);
            $storage = Storage::disk("hr_system_folder");

            $offenses->getCollection()->transform(function ($offense) use ($storage) {
            $offense->attachments->transform(function ($attachment) use ($storage) {
                $path = $attachment->attachment;

                if ($storage->exists($path)) {
                    $mimeType = $storage->mimeType($path);
                    $fileContents = $storage->get($path);

                    $attachment->attached_file =
                        'data:' . $mimeType . ';base64,' . base64_encode($fileContents);
                } else {
                    $attachment->attached_file = null;
                }

                return $attachment;
            });

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

    public function addOffense(Request $request, $employee_number)
    {
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
                'attachments' => "nullable|array",
                'attachments.*' => "file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx",
            ]);

            $employee = Employee::where("employee_number", $employee_number)->firstOrFail();

            $offense = $employee->offenses()->create(
                $request->except(["attachments"])
            );

            if ($request->hasFile("attachments")) {

                $storage = Storage::disk("hr_system_folder");

                $directory = "/Files Per Employee/" . $employee->employee_number;

                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }

                foreach ($request->file("attachments") as $file) {

                    $fileName = uniqid() . '.' . $file->getClientOriginalExtension();

                    $filePath = $directory . '/' . $fileName;

                    $storage->put($filePath, file_get_contents($file));

                    $offense->attachments()->create([
                        "attachment" => $filePath,
                        "attachment_file_name" => $file->getClientOriginalName(),
                    ]);
                }
            }

            DB::commit();

            return $this->sendResponse([
                "message" => "Offense added successfully."
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            return $this->sendError([
                "message" => $e->getMessage()
            ]);
        }
    }

    public function editOffense(Request $request, $employee_number)
    {
    try {
        DB::beginTransaction();
        $request->validate([
            "id" => "required",
            "offense_id" => "required",
            "sanction_id" => "required",
            "transgression" => "required",
            "repetition" => "required",
            "summary" => "required",
            "date_committed" => "required",
            "offense_action" => "required",
            "date_served_ao" => "required",
            "suspension_dates" => "required",
            "date_served_da" => "required",
            "attachments" => "nullable|array",
            "attachments.*" => "file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx",
            "removed_attachment_ids" => "nullable|array",
            "removed_attachment_ids.*" => "integer",
        ]);
        $employee = Employee::where("employee_number", $employee_number)
            ->firstOrFail();

        $offense = $employee->offenses()
            ->where("id", $request->id)
            ->firstOrFail();

        // Update offense details
        $offense->update(
            $request->except([
                "id",
                "attachments",
                "removed_attachment_ids",
            ])
        );
        $storage = Storage::disk("hr_system_folder");
        $directory = "/Files Per Employee/" . $employee->employee_number;
        if (!empty($request->removed_attachment_ids)) {

            $attachments = $offense->attachments()
                ->whereIn("id", $request->removed_attachment_ids)
                ->get();

            foreach ($attachments as $attachment) {

                if ($storage->exists($attachment->attachment)) {
                    $storage->delete($attachment->attachment);
                }

                $attachment->delete();
            }
        }
        if ($request->hasFile("attachments")) {

            if (!$storage->exists($directory)) {
                $storage->makeDirectory($directory);
            }

            foreach ($request->file("attachments") as $file) {

                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();

                $filePath = $directory . '/' . $fileName;

                $storage->put($filePath, file_get_contents($file));

                $offense->attachments()->create([
                    "attachment" => $filePath,
                    "attachment_file_name" => $file->getClientOriginalName(),
                ]);
            }
        }
        DB::commit();
        $offense->load([
            "attachments",
            "offense",
            "sanction",
        ]);
        return $this->sendResponse([
            "message" => "Offense updated successfully.",
            "offense" => $offense,
        ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError([
                "message" => $e->getMessage()
            ]);
        }
    }
}
