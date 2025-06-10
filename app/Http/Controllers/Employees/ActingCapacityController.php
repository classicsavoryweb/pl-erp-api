<?php

namespace App\Http\Controllers\Employees;

use App\Exports\Employees\ActingCapacityExport;
use App\Http\Controllers\Controller;
use App\Models\ActingCapacity;
use App\Models\Employee;
use Exception;
use Illuminate\Http\Request;
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
                    "branch"
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
                "recommendation_letter" => "required"
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
                "status" => "Pending"
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
                "extensions"
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
}
