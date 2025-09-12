<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\LastPaySetupExport;
use App\Http\Controllers\Controller;
use App\Models\LastPaySetup;
use Illuminate\Http\Request;
use Str;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class LastPaySetupController extends Controller
{
    public function getAllLastPaySetup(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $last_pay = LastPaySetup::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    $tableMap = [
                        'debit'  => ['table' => 'apsys.tbl_account', 'foreign_key' => 'debit_id', 'primary_key' => 'acc_no'],
                        'credit' => ['table' => 'apsys.tbl_account', 'foreign_key' => 'credit_id', 'primary_key' => 'acc_no'],
                    ];

                    if (isset($tableMap[$relation])) {
                        $map = $tableMap[$relation];
                        $last_pay = $last_pay->whereExists(function ($query) use ($map, $relatedColumn, $keyword) {
                            $query->selectRaw(1)
                                ->from($map['table'])
                                ->whereColumn("last_pay_setup.{$map['foreign_key']}", "{$map['table']}.{$map['primary_key']}")
                                ->where("{$map['table']}.$relatedColumn", 'LIKE', "%{$keyword}%");
                        });
                    } else {
                        $last_pay = $last_pay->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%{$keyword}%");
                        });
                    }
                } else {
                    $last_pay = $last_pay->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["export"]) && $request->export) {
                $last_pay = $last_pay->get();
                return Excel::download(new LastPaySetupExport($last_pay), "last_pay.xls");
            }

            $last_pay = $last_pay
            ->with(["debit", "credit"])
            ->paginate($perPage);
            return $this->sendResponse($last_pay);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllLastPaySetup(Request $request) {
        $request["export"] = true;

        return $this->getAllLastPaySetup($request);
    }

    public function getLastPaySetup(Request $request) {
        try {
            $last_pay = LastPaySetup::all();
            return $this->sendResponse(["last_pay" => $last_pay]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editLastPaySetup(Request $request, $id) {
        try {
            $request->validate([
                "debit_id" => "required",
                "credit_id" => "required",
            ]);

            LastPaySetup::where("id", $id)->update([
                "debit_id" => $request->debit_id,
                "credit_id" => $request->credit_id,
            ]);

            return $this->sendResponse(["message" => "Last pay updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
