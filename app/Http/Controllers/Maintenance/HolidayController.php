<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\HolidayExport;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class HolidayController extends Controller
{
    public function getAllHolidays(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $holidays = Holiday::query();

            if ($request->has(["column_type", "key_word"])) {
               $holidays = $holidays->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["date_from", "date_to"])) {
                $holidays = $holidays->whereBetween('date', [$request->date_from, $request->date_to]);
            }

            if ($request->has(["holiday_type"])) {
                $holidays = $holidays->where("holiday_type", $request->holiday_type);
            }
            
            if ($request->has(["export"]) && $request->export) {
                $holidays = $holidays->get();
                return Excel::download(new HolidayExport($holidays), "holidays.xls");
            }

            $holidays = $holidays->orderBy("date", "DESC")
            ->with(["branches"])
            ->paginate($perPage);

            return $this->sendResponse($holidays);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportHolidays(Request $request) {
        $request["export"] = true;

        return $this->getAllHolidays($request);
    }

    public function getHoliday(Request $request) {
        try {
            $holiday = Holiday::all();
            return $this->sendResponse(["holiday" => $holiday]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    
    public function createHoliday(Request $request)
    {
        try {
            DB::beginTransaction();
            $request->validate([
                "date" => "required",
                "holiday_type" => "required",
                "branch_id" => "required|array",
                "description" => "required",
                "required_days" => "required",
                "required_for" => "required"
            ]);

            $holiday = Holiday::create([
                "date" => $request->date,
                "holiday_type" => $request->holiday_type,
                "description" => $request->description,
                "required_days" => $request->required_days,
                "required_for" => $request->required_for,
            ]);

            $holiday->branches()->attach($request->branch_id);

            DB::commit();
            return $this->sendResponse(["message" => "Holiday created successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editHoliday(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $request->validate([
                "date" => "required",
                "holiday_type" => "required",
                "branch_id" => "required|array",
                "description" => "required",
                "required_days" => "required",
                "required_for" => "required"
            ]);

            $holiday = Holiday::find( $id);

            $holiday->update([
                "date" => $request->date,
                "holiday_type" => $request->holiday_type,
                "description" => $request->description,
                "required_days" => $request->required_days,
                "required_for" => $request->required_for,
            ]);

            $holiday->branches()->attach($request->branch_id);
            DB::commit();
            return $this->sendResponse(["message" => "Holiday updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
