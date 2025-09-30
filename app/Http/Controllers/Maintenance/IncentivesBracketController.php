<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\IncentivesBracketExport;
use App\Http\Controllers\Controller;
use App\Models\IncentivesBracket;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class IncentivesBracketController extends Controller
{
    public function getAllIncentivesBracket(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;
            $page = $request->page ?? 1;

            $incentives_bracket = IncentivesBracket::query();

            if ($request->has(["column_type", "key_word"])) {
               $incentives_bracket = $incentives_bracket->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["export"]) && $request->export) {
                $incentives_bracket = $incentives_bracket->get();
                return Excel::download(new IncentivesBracketExport($incentives_bracket), "incentives_bracket.xls");
            }

            $grouped = $incentives_bracket->with('positions')
                ->get()
                ->groupBy('incentives_bracket_id')
                ->map(function ($group) {
                    return [
                        'incentives_bracket_id' => $group->first()->incentives_bracket_id,
                        'bracket_name' => $group->first()->bracket_name,
                        'increments' => $group->map(function ($item) {
                            return [
                                'month' => $item->month,
                                'amount' => $item->amount,
                            ];
                        })->sortBy('month')->values(),
                        'positions' => $group->first()->positions,
                    ];
                })
                ->values();

            $paginated = new LengthAwarePaginator(
                $grouped->forPage($page, $perPage)->values(),
                $grouped->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'incentives_bracket' => $request->query()]
            );

            return $this->sendResponse($paginated);

        } catch (Exception $e) {
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function exportAllIncentivesBracket(Request $request) {
        $request["export"] = true;

        return $this->getAllIncentivesBracket($request);
    }

    public function getIncentivesBracket(Request $request) {
        try {
            $incentives_bracket = IncentivesBracket::all();
            return $this->sendResponse(["incentives_bracket" => $incentives_bracket]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    
    public function createIncentivesBracket(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'bracket_name' => 'required',
                'increments' => 'required|array',
                'position_id' => 'required|array',
            ]);

            $incentives_bracket_id = (int) IncentivesBracket::max('incentives_bracket_id') + 1;

            foreach ($request->increments as $increment) {
                $amount = str_replace(',', '', $increment['amount']);

                IncentivesBracket::create([
                    'incentives_bracket_id' => $incentives_bracket_id,
                    'bracket_name' => $request->bracket_name,
                    'month' => $increment['month'],
                    'amount' => $amount,
                ]);
            }

            $firstBracket = IncentivesBracket::where('incentives_bracket_id', $incentives_bracket_id)->first();

            $firstBracket->positions()->attach($request->position_id);

            DB::commit();
            return $this->sendResponse(['message' => 'Incentives bracket created successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(['message' => $e->getMessage()]);
        }
    }

    public function editIncentivesBracket(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                "incentives_bracket_id" => "required",
                "bracket_name" => "required",
                "increments" => "required|array",
                "position_id" => "required|array",
            ]);

            $submitted = collect($request->increments)->keyBy('month');

            foreach ($submitted as $month => $data) {
                $amount = str_replace(',', '', $data['amount']);

                IncentivesBracket::where("incentives_bracket_id", $request->incentives_bracket_id)
                    ->where("month", $month)
                    ->update([
                        'bracket_name' => $request->bracket_name,
                        'amount' => $amount,
                    ]);
            }

            $incentivesBracket = IncentivesBracket::find($request->incentives_bracket_id);

            $incentivesBracket->positions()->sync($request->position_id);

            DB::commit();
            return $this->sendResponse(["message" => "Incentives bracket updated successfully"]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
