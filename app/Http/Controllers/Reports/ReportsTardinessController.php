<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTardinessImport;
use App\Models\Employee;
use App\Models\EmployeeOffense;
use App\Models\Offense;
use App\Models\Tardiness;
use App\Models\TardinessMinutes;
use App\Models\TardinessSanction;
use Illuminate\Http\Request;
use Exception;
use DB;
use Illuminate\Support\Facades\Storage;
use Str;
use Carbon\Carbon;

class ReportsTardinessController extends Controller
{
    public function getAllReportsVaccines(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $tardiness = Tardiness::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    if ($relatedColumn == "full_name") {
                        $tardiness = $tardiness->whereHas($relation, function ($query) use ($keyword) {
                            $query->whereAny(
                                ["first_name", "last_name", "middle_name"],
                                'LIKE',
                                "%$keyword%"
                            )->orWhere(function ($q) use ($keyword) {
                                $q->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            });
                        });
                    } else {
                        $tardiness = $tardiness->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    }

                } else {
                    $tardiness = $tardiness->where($column, 'LIKE', "%$keyword%");
                }
            }

            $tardiness = $tardiness->orderByRaw("CASE WHEN status = 'Pending' THEN 0 ELSE 1 END");

            $tardiness = $tardiness->with([
                "tardiness_sanction",
                "employee"
            ])
            ->orderBy("id","DESC")
            ->paginate($perPage);

            return $this->sendResponse($tardiness);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function approveTardiness (Request $request, $id) {
        try {
            DB::beginTransaction();
            $tardiness = Tardiness::find($id);
            $employee = Employee::find($tardiness->employee_id);

            if ($tardiness->status === "Pending") {
                $offense = Offense::where("offense_number", "0000210")->first();

                $lastTardiness = Tardiness::where("employee_id", $employee->id)
                ->where("status", "Approved")
                ->where("year", Carbon::now()->year)
                ->latest()
                ->first();

                $lastSanction = "";
                if ($lastTardiness) {
                    $lastSanction = $lastTardiness->tardiness_sanction()->first();
                }

                if ($lastSanction) {
                    $rank = $lastSanction->rank !== 12 ? $lastSanction->rank + 1 : $lastSanction->rank;
                    $sanction = TardinessSanction::where("rank", $rank)->first();
                } else {
                    $sanction = TardinessSanction::where("rank", 1)->first();
                }

                $tardiness->update([
                    "status" => "Approved"
                ]);

                EmployeeOffense::create([
                    'offense_id' => $offense->id,
                    'employee_id' => $employee->id,
                    'sanction_id' => $sanction->id,
                    'transgression' => $offense->subcategory,
                    'repetition' => $sanction->count,
                    'summary' => "Total Minutes Tardy: ". $tardiness->total_minutes ." minutes late",
                    'date_committed' => $tardiness->year . '-' . str_pad($tardiness->month, 2, '0', STR_PAD_LEFT) . '-01',
                    'tardiness_id' => $tardiness->id,
                    'remarks' => $tardiness->reason,
                ]);
                
            } else {
                return $this->sendError(["message" => "Tardiness cant be approved"]);
            }

            DB::commit();
            return $this->sendResponse(["message" => "Tardiness approved successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function voidTardiness (Request $request, $id) {
        try {
            DB::beginTransaction();
            $tardiness = Tardiness::find($id);

            if ($tardiness->status === "Voided") {
                return $this->sendError(["message" => "Tardiness cant be voided"]);
            } else {
                if ($tardiness->status === "Approved") {
                    EmployeeOffense::where("tardiness_id", $tardiness->id)
                    ->delete();
                }
                $tardiness->update([
                    "status" => "Voided"
                ]);
            }

            DB::commit();
            return $this->sendResponse(["message" => "Tardiness voided successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function importTardinessExcel (Request $request) {
        try {
            $request->validate([
                "tardiness_file" => "required"
            ]);

            if ($request->hasFile("tardiness_file")) {
                $storage = Storage::disk("hr_system_folder");
                $directory = "/imports";
                $file = $request->file("tardiness_file");
                if (!$storage->exists($directory)) {
                    $storage->makeDirectory($directory);
                }

                $fileName = uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $directory . '/' . $fileName;
                $storage->put($filePath, file_get_contents($file));
                
                ProcessTardinessImport::dispatch($filePath);
            }
            return $this->sendResponse(["message" => "Excel file imported successfullyy"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getTardinessDetails ($id) {
        try {
            $tardiness = Tardiness::find($id);
            $tardiness_minutes = TardinessMinutes::where("employee_id", $tardiness->employee_id)
            ->where("month", $tardiness->month)
            ->where("year", $tardiness->year)
            ->get();
            $tardiness["tardiness_minutes"] = $tardiness_minutes;

            return $this->sendResponse($tardiness);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function approveBulkTardiness(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "tardiness_id" => "required"
            ]);

            foreach($request->tardiness_id as $tardy) {
                $tardiness = Tardiness::find($tardy);
                $employee = Employee::find($tardiness->employee_id);

                if ($tardiness->status === "Pending") {
                    $offense = Offense::where("offense_number", "0000210")->first();

                    $lastTardiness = Tardiness::where("employee_id", $employee->id)
                    ->where("status", "Approved")
                    ->where("year", Carbon::now()->year)
                    ->latest()
                    ->first();

                    $lastSanction = "";
                    if ($lastTardiness) {
                        $lastSanction = $lastTardiness->tardiness_sanction()->first();
                    }

                    if ($lastSanction) {
                        $rank = $lastSanction->rank !== 12 ? $lastSanction->rank + 1 : $lastSanction->rank;
                        $sanction = TardinessSanction::where("rank", $rank)->first();
                    } else {
                        $sanction = TardinessSanction::where("rank", 1)->first();
                    }

                    $tardiness->update([
                        "status" => "Approved"
                    ]);

                    EmployeeOffense::create([
                        'offense_id' => $offense->id,
                        'employee_id' => $employee->id,
                        'sanction_id' => $sanction->id,
                        'transgression' => $offense->subcategory,
                        'repetition' => $sanction->count,
                        'summary' => "Total Minutes Tardy: ". $tardiness->total_minutes ." minutes late",
                        'date_committed' => $tardiness->year . '-' . str_pad($tardiness->month, 2, '0', STR_PAD_LEFT) . '-01',
                        'tardiness_id' => $tardiness->id,
                        'remarks' => $tardiness->reason,
                    ]);
                    
                }
            }

            DB::commit();
            return $this->sendResponse(["message" => "Selected tardiness approved successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editMinutesOfTardiness(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "tardiness_minutes" => "required"
            ]);

            foreach($request->tardiness_minutes as $minutes) {
                TardinessMinutes::where("id", $minutes["id"])
                ->update([
                    "late_minutes" => $minutes["late_minutes"]
                ]);
            }
            
            DB::commit();
            return $this->sendResponse(["message" => "Tardiness minutes updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
