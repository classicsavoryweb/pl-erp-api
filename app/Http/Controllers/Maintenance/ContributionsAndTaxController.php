<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\ContributionAndTaxExport;
use App\Http\Controllers\Controller;
use App\Models\ContributionAndTax;
use App\Models\Vendor;
use App\Models\Account;
use Illuminate\Http\Request;
use Str;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class ContributionsAndTaxController extends Controller
{
    public function getAllContributionsAndTax(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $contributions_and_tax = ContributionAndTax::query();
            
            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);

                    $tableMap = [
                        'vendor' => ['table' => 'apsys.tbl_vendor', 'foreign_key' => 'vendor_id', 'primary_key' => 'ven_no'],
                        'debit'  => ['table' => 'apsys.tbl_account', 'foreign_key' => 'debit_id', 'primary_key' => 'acc_no'],
                        'credit' => ['table' => 'apsys.tbl_account', 'foreign_key' => 'credit_id', 'primary_key' => 'acc_no'],
                    ];

                    if (isset($tableMap[$relation])) {
                        $map = $tableMap[$relation];
                        $contributions_and_tax = $contributions_and_tax->whereExists(function ($query) use ($map, $relatedColumn, $keyword) {
                            $query->selectRaw(1)
                                ->from($map['table'])
                                ->whereColumn("contributions_and_tax.{$map['foreign_key']}", "{$map['table']}.{$map['primary_key']}")
                                ->where("{$map['table']}.$relatedColumn", 'LIKE', "%{$keyword}%");
                        });
                    } else {
                        $contributions_and_tax = $contributions_and_tax->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%{$keyword}%");
                        });
                    }
                } else {
                    $contributions_and_tax = $contributions_and_tax->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["export"]) && $request->export) {
                $contributions_and_tax = $contributions_and_tax->get();
                return Excel::download(new ContributionAndTaxExport($contributions_and_tax), "contributions_and_tax.xls");
            }

            $contributions_and_tax = $contributions_and_tax
            ->with(["vendor", "debit", "credit"])
            ->paginate($perPage);
            return $this->sendResponse($contributions_and_tax);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllContributionsAndTax(Request $request) {
        $request["export"] = true;

        return $this->getAllContributionsAndTax($request);
    }

    public function getContributionAndTax(Request $request) {
        try {
            $contributions_and_tax = ContributionAndTax::all();
            return $this->sendResponse(["contributions_and_tax" => $contributions_and_tax]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editContributionAndTax(Request $request, $id) {
        try {
            $request->validate([
                "vendor_id" => "required",
                "debit_id" => "required",
                "credit_id" => "required",
            ]);

            ContributionAndTax::where("id", $id)->update([
                "vendor_id" => $request->vendor_id,
                "debit_id" => $request->debit_id,
                "credit_id" => $request->credit_id,
            ]);

            return $this->sendResponse(["message" => "Contribution and tax updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
