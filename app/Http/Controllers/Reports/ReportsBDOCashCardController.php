<?php

namespace App\Http\Controllers\Reports;

use App\Exports\Reports\ReportsBDOCashCardExport;
use App\Exports\Reports\ReportsBDOCashCardHistoryExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;
use Str;
use DB;

class ReportsBDOCashCardController extends Controller
{
    public function getAllReportsBDOCashCard(Request $request)
    {
        try {
            $perPage = $request->per_page ??10;
            $bdoCashCard = Employee::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if ($column == "full_name") {
                    $bdoCashCard = $bdoCashCard->where(function ($query) use ($keyword) {
                        $query->whereAny(
                            ["first_name", "last_name", "middle_name"],
                            'LIKE',
                            "%$keyword%"
                        )->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            }
                        )->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                            }
                        );
                    });
                } else {
                    if (Str::contains($column, '.')) {
                        [$relation, $relatedColumn] = explode('.', $column, 2);

                        $bdoCashCard = $bdoCashCard->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    } else {
                        $bdoCashCard = $bdoCashCard->where($column, 'LIKE', "%$keyword%");
                    }
                }
            }

            $bdoCashCard = $bdoCashCard->with([
                "latest_bank_details", 
                "latest_bank_details.bank_account_type"
            ]);

            if ($request->has(["export"]) && $request->export) {
                $bdoCashCard = $bdoCashCard->get();
                return Excel::download(new ReportsBDOCashCardExport($bdoCashCard), "BDO_Cash_Card.xls");
            }

            $bdoCashCard = $bdoCashCard->paginate($perPage);

            return $this->sendResponse($bdoCashCard);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllReportsBDOCashCard(Request $request) {
        $request["export"] = true;

        return $this->getAllReportsBDOCashCard($request);
    }

    public function getAllReportsBDOCashCardHistory(Request $request)
    {
        try {
            $perPage = $request->per_page ??10;
            $bdoCashCard = Employee::query();

            $bdoCashCard = $bdoCashCard->with([
                "bank_details",
                "latest_bank_details", 
                "latest_bank_details.bank_account_type"
            ]);

            if ($request->has(["export"]) && $request->export) {
                $bdoCashCard = $bdoCashCard->get();
                return Excel::download(new ReportsBDOCashCardHistoryExport($bdoCashCard), "BDO_Cash_Card_History.xls");
            }

            $bdoCashCard = $bdoCashCard->paginate($perPage);

            return $this->sendResponse($bdoCashCard);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllReportsBDOCashCardHistory(Request $request) {
        $request["export"] = true;

        return $this->getAllReportsBDOCashCardHistory($request);
    }
}
