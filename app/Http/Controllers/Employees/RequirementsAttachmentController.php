<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\RequirementsAttachment;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Storage;
use DB;
use ZipArchive;
use Str;
class RequirementsAttachmentController extends Controller
{
    public function uploadAttachments(Request $request, $employee_number){
        try {
            DB::beginTransaction();
            $employee = Employee::where("employee_number", $employee_number)->first();

            $request->validate([
                "attachments" => "required"
            ]);

            $storage = Storage::disk("hr_system_folder");
            if ($request->has("attachments")) {
                foreach($request->attachments as $attachment) {
                    $pathName = "";
                    $directory = "/Requirements Files/" . $employee->employee_number;
                    $file = $attachment["file"];

                    if (!$storage->exists($directory)) {
                        $storage->makeDirectory($directory);
                    }

                    $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                    $filePath = $directory . '/' . $fileName;
                    $storage->put($filePath, file_get_contents($file));
                    $pathName = $filePath;

                    RequirementsAttachment::create([
                        "file_path" => $pathName,
                        "file_name" => $attachment["file_name"],
                        "employee_id" => $employee->id
                    ]);
                }
            }

            DB::commit();
            return $this->sendResponse(["message" => "Attachments uploaded successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getAttachments(Request $request, $employee_number) {
        try {
            DB::beginTransaction();
            $employee = Employee::where("employee_number", $employee_number)->first();
            $attachments = RequirementsAttachment::where("employee_id", $employee->id)->get();
            $storage = Storage::disk("hr_system_folder");

            if ($attachments) {
                foreach($attachments as $attach) {
                    $fileContents = $storage->get($attach->file_path);

                    $mimeType = $storage->mimeType($attach->file_path);
                    $attach->file_path ='data:'. $mimeType .';base64,' . base64_encode($fileContents);
                }
            }

            DB::commit();
            return $this->sendResponse(["attachments" => $attachments]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportRequirements($employee_number) {
        try {
            $employee = Employee::where("employee_number", $employee_number)->first();
            $attachments = RequirementsAttachment::where("employee_id", $employee->id)->get();
            $storage = Storage::disk("hr_system_folder");

            $zipFileName = $employee_number.'-REQUIREMENTS-DOCUMENTS.zip';
            $zipPath = storage_path("app/temp/$zipFileName");

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                foreach ($attachments as $file) {
                    if ($storage->exists($file->file_path)) {
                        $zip->addFromString(
                            basename($file->file_path),
                            $storage->get($file->file_path)
                        );
                    }
                }
                $zip->close();

                return response()->download($zipPath)->deleteFileAfterSend(true);
            } else {
                return $this->sendError(["message" => "Zipping files error encountered" ]);
            }

        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
