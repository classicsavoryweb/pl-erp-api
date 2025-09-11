<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\TermExport;
use App\Http\Controllers\Controller;
use App\Models\Term;
use Illuminate\Http\Request;
use DB;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class TermsController extends Controller
{
    public function getAllTerms(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $terms = Term::query();
            if ($request->has(["column_type", "key_word"])) {
                $terms = $terms->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["export"]) && $request->export) {
                $terms = $terms->get();
                return Excel::download(new TermExport($terms), "terms.xls");
            }

            $terms = $terms->paginate($perPage);
            return $this->sendResponse($terms);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportAllTerms(Request $request) {
        $request["export"] = true;

        return $this->getAllTerms($request);
    }

    public function getTerm(Request $request) {
        try {
            $terms = Term::where("status", "active")->get();
            return $this->sendResponse(["terms" => $terms]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createTerm(Request $request) {
        try {
            $request->validate([
                "description" => "required",
                "cutoff" => "required",
                "amount_from" => "required",
                "amount_to" => "required",
                "month" => "required",
            ]);

            Term::create([
                "description" => $request->description,
                "cutoff" => $request->cutoff,
                "amount_from" => $request->amount_from,
                "amount_to" => $request->amount_to,
                "month" => $request->month,
            ]);

            return $this->sendResponse(["message" => "Term created succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editTerm(Request $request, $id) {
        try {
            $request->validate([
                "description" => "required|unique:terms,description,".$id,
                "cutoff" => "required",
                "amount_from" => "required",
                "amount_to" => "required",
                "month" => "required",
            ]);

            Term::where("id", $id)->update([
                "description" => $request->description,
                "cutoff" => $request->cutoff,
                "amount_from" => $request->amount_from,
                "amount_to" => $request->amount_to,
                "month" => $request->month,
            ]);

            return $this->sendResponse(["message" => "Term updated succesfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
