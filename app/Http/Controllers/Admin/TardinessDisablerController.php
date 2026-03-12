<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TardinessDisabler;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Exception;

class TardinessDisablerController extends Controller
{
    public function getAllTardinessDisablers(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;
            $onlyDisabled = filter_var($request->only_disabled, FILTER_VALIDATE_BOOLEAN);

            $query = TardinessDisabler::query()
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc');

            if ($onlyDisabled) {
                $query->where('is_disabled', true);
            }

            if ($request->has('page')) {
                $data = $query->paginate($perPage);
                return $this->sendResponse($data);
            }

            return $this->sendResponse($query->get());
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function upsertTardinessDisabler(Request $request)
    {
        try {
            $request->validate([
                "month" => "required|integer|min:1|max:12",
                "year" => "required|integer|min:2000|max:2100",
                "is_disabled" => "required|boolean",
            ]);

            $record = TardinessDisabler::updateOrCreate(
                [
                    "month" => (int) $request->month,
                    "year" => (int) $request->year,
                ],
                [
                    "is_disabled" => (bool) $request->is_disabled,
                ]
            );

            $status = $record->is_disabled ? "disabled" : "enabled";
            $label = Carbon::create($record->year, $record->month, 1)->format('F Y');

            return $this->sendResponse([
                "message" => "Tardiness for {$label} is now {$status}.",
                "record" => $record,
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
