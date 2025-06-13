<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\ActingCapacityExport;
use App\Http\Controllers\Controller;
use App\Models\ActingCapacity;
use App\Models\ActingCapacityAttachments;
use App\Models\ActingCapacityExtension;
use App\Models\Employee;
use App\Models\EmployeePosition;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Str;
use DB;

class ActingCapacityController extends Controller
{
    public function getAllActingCapacity(Request $request) {
        try {
            $perPage = $request->per_page ??10;

            $actingCapacity = ActingCapacity::with([
                    "employee.current_branch",
                    "employee.employee_status",
                    "current_position",
                    "recommended_position",
                    "branch",
                    "branch_assignment"
                ])
                ->orderBy("created_at","DESC");

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if ($column == "employee.full_name") {
                    $actingCapacity = $actingCapacity->whereHas("employee",function ($query) use ($keyword) {
                        $query->whereAny(
                            ["first_name", "last_name", "middle_name"],
                            'LIKE',
                            "%$keyword%"
                        )->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            }
                        );
                    });
                } else {
                    if (Str::contains($column, '.')) {
                        [$relation, $relatedColumn] = explode('.', $column, 2);

                        $actingCapacity = $actingCapacity->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    } else {
                        $actingCapacity = $actingCapacity->where($column, 'LIKE', "%$keyword%");
                    }
                }
            }

            if ($request->has(["branch_id"])) {
                $actingCapacity = $actingCapacity->whereHas("employee", function ($query) use ($request) {
                    $query->where("branch_id", $request->branch_id);
                });
            }

            if ($request->has(["date_from", "date_to"])) {
                $actingCapacity = $actingCapacity->whereBetween('confirmed_date', [$request->date_from, $request->date_to]);
            }

            if ($request->has(["status"])) {
                $actingCapacity = $actingCapacity->where("status", $request->status);
            }

            if($request->has(["export"]) && $request->export) {
                $actingCapacity = $actingCapacity->get();
                return Excel::download(new ActingCapacityExport($actingCapacity), "acting-capacity.xls");
            }

            $actingCapacity = $actingCapacity->paginate($perPage);

            return $this->sendResponse($actingCapacity);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllActingCapacity(Request $request) {
        $request["export"] = true;

        return $this->getAllActingCapacity($request);
    }

    public function addNewActingCapacity(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "employee_number" => "required",
                "recommended_position_id" => "required",
                "start_date" => "required",
                "end_date" => "required",
                "recommendation_letter" => "required",
                "oic_employee_number" => "required",
                "branch_assignment_id" => "required"
            ]);
            
            $employee = Employee::where("employee_number", $request->employee_number)->first();

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $months = $startDate->diffInMonths($endDate);

            $pathName = "";
            if ($request->hasFile("recommendation_letter")) {
                $file = $request->file("recommendation_letter");

                $storage = Storage::disk("hr_system_folder");
                $directory = "/Acting Capacity Files/" . $employee->employee_number;
                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }
                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));
                $pathName = $filePath;
            }

            ActingCapacity::create([
                "employee_id" => $employee->id,
                "branch_id" => $employee->branch_id,
                "current_position_id" => $employee->position_id,
                "recommended_position_id" => $request->recommended_position_id,
                "start_date" => $request->start_date,
                "end_date" => $request->end_date,
                "period" => $months > 1 ? $months. " Months" : $months . " Month",
                "remarks" => $request->remarks ?? null,
                "recommendation_letter" => $pathName,
                "status" => "Pending",
                "oic_employee_number" => $request->oic_employee_number,
                "branch_assignment_id" => $request->branch_assignment_id
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Acting capacity saved successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getActingCapacity(Request $request, $id) {
        try {
            $actingCapacity = ActingCapacity::with([
                "employee.employee_status",
                "attachments",
                "current_position",
                "recommended_position",
                "branch",
                "extensions",
                "reject",
                "confirm",
                "generate",
                "branch_assignment"
            ])->find($id);

            $storage = Storage::disk("hr_system_folder");
            if ($actingCapacity->recommendation_letter) {
                $fileContents = $storage->get($actingCapacity->recommendation_letter);

                $mimeType = $storage->mimeType($actingCapacity->recommendation_letter);
                $actingCapacity->recommendation_letter ='data:'. $mimeType .';base64,' . base64_encode($fileContents);
            }
            if ($actingCapacity->attachments) {
                foreach($actingCapacity->attachments as $attach) {
                    $fileContents = $storage->get($attach->file_path);

                    $mimeType = $storage->mimeType($attach->file_path);
                    $attach->file_path ='data:'. $mimeType .';base64,' . base64_encode($fileContents);
                }
            }

            return $this->sendResponse(["acting_capacity" => $actingCapacity]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getACFDetails (Request $request, $id) {
        try {
            $actingCapacity = ActingCapacity::with([
                "employee.employee_status",
                "employee.current_branch",
                "employee.current_position",
                "current_position",
                "recommended_position",
                "branch",
                "branch.group_head",
                "branch.branch_oic",
                "branch_assignment",
                "oic",
                "extensions"
            ])->find($id);
            
            $latestExtension = $actingCapacity->extensions()->latest()->first();
            if ($latestExtension) {
                $actingCapacity->end_date = $latestExtension->end_date;

            }

            $station = $actingCapacity->current_position->station->name;
            $type = "english";
            $withRecommendation = false;
            if ( strtolower($station) === "back of the house") {
                $type = "tagalog";
            } else if (strtolower($station) === "head office") {
                $withRecommendation = true;
            }

            return $this->sendResponse([
                "acting_capacity" => $actingCapacity,
                "with_recommendation" => $withRecommendation,
                'type' => $type
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editActingCapacity(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "id" => "required",
                "employee_number" => "required",
                "recommended_position_id" => "required",
                "start_date" => "required",
                "end_date" => "required",
                "oic_employee_number" => "required",
                "branch_assignment_id" => "required",
            ]);
            
            $employee = Employee::where("employee_number", $request->employee_number)->first();
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $months = $startDate->diffInMonths($endDate);

            if ($request->has("extensions") && is_array($request->extensions)) {
                $extensions = $request->extensions;
                $lastExtensionDate = end($extensions);
                $extendedEndDate = Carbon::parse($lastExtensionDate['end_date']);
                $months = $startDate->diffInMonths($extendedEndDate);
            }

            $pathName = "";
            if ($request->hasFile("recommendation_letter")) {
                $file = $request->file("recommendation_letter");

                $storage = Storage::disk("hr_system_folder");
                $directory = "/Acting Capacity Files/" . $employee->employee_number;
                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }
                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));
                $pathName = $filePath;
            }

            $actingCapacity = ActingCapacity::find( $request->id);

            $actingCapacity->update([
                "employee_id" => $employee->id,
                "branch_id" => $employee->branch_id,
                "current_position_id" => $employee->position_id,
                "recommended_position_id" => $request->recommended_position_id,
                "start_date" => $request->start_date,
                "end_date" => $request->end_date,
                "period" => $months > 1 ? $months. " Months" : $months . " Month",
                "remarks" => $request->remarks ?? null,
                "recommendation_letter" => $pathName,
                "status" => "Pending",
                "oic_employee_number" => $request->oic_employee_number,
                "branch_assignment_id" => $request->branch_assignment_id
            ]);

            if ($request->has("extensions")) {
                $extensions = $request->extensions;
                foreach($extensions as $ext) {
                    if ( isset($ext['id'])) {
                        ActingCapacityExtension::where("id", $ext['id'])
                        ->update([
                            "acting_capacity_id" => $actingCapacity->id,
                            "end_date" => $ext['end_date'],
                            "remarks" => $ext['remarks'],
                            "reason" => $ext['reason']
                        ]);
                    } else {
                        ActingCapacityExtension::create([
                            "acting_capacity_id" => $actingCapacity->id,
                            "end_date" => $ext['end_date'],
                            "remarks" => $ext['remarks'],
                            "reason" => $ext['reason']
                        ]);
                    }
                }
            }

            DB::commit();
            return $this->sendResponse(["message" => "Acting capacity updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function generateConfirmationDocuments(Request $request, $id) {
        try {
            DB::beginTransaction();
            $request->validate([
                "effectivity_date" => "required",
                "bond_amount" => "required",
                "bond_year" => "required"
            ]);

            $employee = Employee::where("employee_number", Auth::user()->employee_number)->first();
            $actingCapacity = ActingCapacity::find($id);
            $actingCapacity->update([
                "effectivity_date" => $request->effectivity_date,
                "bond_amount" => $request->bond_amount,
                "bond_year" => $request->bond_year,
                "generated_by" => $employee->id,
                "generated_date" => now(),
                "status" => "For Signature"
            ]);

            DB::commit();
            return $this->getACFDetails($request, $id);
        } catch(Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function confirmPromotion(Request $request, $id) {
        try {
            DB::beginTransaction();

            $request->validate([
                "attachments" => "required"
            ]);

            $employee = Employee::where("employee_number", Auth::user()->employee_number)->first();
            $actingCapacity = ActingCapacity::find($id);

            if ($actingCapacity->status !== "Confirmed" && $actingCapacity->status !== "Rejected") {
                $actingCapacity->update([
                    "confirmed_by" => $employee->id,
                    "confirmed_date" => now(),
                    "status" => "Confirmed"
                ]);

                $employee->update([
                    'position_id' => $actingCapacity->recommended_position_id
                ]);

                EmployeePosition::create([
                    "employee_id" => $employee->id,
                    "oic_assignment" => "No",
                    'position_id' => $actingCapacity->recommended_position_id,
                    "date_started" => $actingCapacity->effectivity_date,
                    "remarks" => "promotion confirmed from acting capacity"
                ]);

                $storage = Storage::disk("hr_system_folder");
                if ($request->has("attachments")) {
                    foreach($request->attachments as $attachment) {
                        $pathName = "";
                        $directory = "/Acting Capacity Files/" . $employee->employee_number;
                        $file = $attachment["file"];

                        if (!$storage->exists($directory)) {
                            $storage->makeDirectory($directory);
                        }

                        $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                        $filePath = $directory . '/' . $fileName;
                        $storage->put($filePath, file_get_contents($file));
                        $pathName = $filePath;

                        ActingCapacityAttachments::create([
                            "file_path" => $pathName,
                            "file_name" => $attachment["file_type"],
                            "acting_capacity_id" => $actingCapacity->id
                        ]);
                    }
                }

                DB::commit();
                return $this->sendResponse(["message" => "Promotion confirmed successfully"]);
            }

            return $this->sendError(["message" => "Promotion already confirmed"]);
        } catch(Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function rejectPromotion(Request $request, $id) {
        try {
            DB::beginTransaction();
            $request->validate([
                "rejected_reason" => "required"
            ]);

            $employee = Employee::where("employee_number", Auth::user()->employee_number)->first();
            $actingCapacity = ActingCapacity::find($id);

            if ($actingCapacity->status !== "Rejected") {
                $actingCapacity->update([
                    "rejected_by" => $employee->id,
                    "rejected_date" => now(),
                    "rejected_reason" => $request->rejected_reason,
                    "status" => "Rejected"
                ]);

                DB::commit();
                return $this->sendResponse(["message" => "Promotion rejected successfully"]);
            }

            return $this->sendError(["message" => "Promotion already rejected"]);
        } catch(Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }


}
