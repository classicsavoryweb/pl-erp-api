<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\ExpenseTypeExport;
use App\Http\Controllers\Controller;
use App\Models\ExpenseType;
use Illuminate\Http\Request;
use Exception;
use Str;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseTypeController extends Controller
{
    public function getAllExpenseTypes(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;

            $expenseTypes = ExpenseType::query();

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if (Str::contains($column, '.')) {
                    [$relation, $relatedColumn] = explode('.', $column, 2);
                    $expenseTypes = $expenseTypes->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                        $query->where($relatedColumn, 'LIKE', "%$keyword%");
                    });
                } else {
                    $expenseTypes = $expenseTypes->where($column, 'LIKE', "%$keyword%");
                }
            }

            if ($request->has(["status"])) {
                $expenseTypes = $expenseTypes->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $expenseTypes = $expenseTypes->with(["debit", "credit"])->get();
                return Excel::download(new ExpenseTypeExport($expenseTypes), "expense_types.xls");
            }

            $expenseTypes = $expenseTypes->with(["debit", "credit"])->paginate($perPage);
            return $this->sendResponse($expenseTypes);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportAllExpenseTypes(Request $request)
    {
        $request["export"] = true;
        return $this->getAllExpenseTypes($request);
    }

    public function getExpenseTypes(Request $request)
    {
        try {
            $expenseTypes = ExpenseType::where("status", "active")->get();
            return $this->sendResponse(["expense_types" => $expenseTypes]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function createExpenseType(Request $request)
    {
        try {
            $request->validate([
                "description" => "required|unique:expense_types",
                "debit_id" => "required",
                "credit_id" => "required",
                "status" => "required",
            ]);

            ExpenseType::create([
                "description" => $request->description,
                "debit_id" => $request->debit_id,
                "credit_id" => $request->credit_id,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Expense type created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function editExpenseType(Request $request, $id)
    {
        try {
            $request->validate([
                "description" => "required|unique:expense_types,description," . $id,
                "debit_id" => "required",
                "credit_id" => "required",
                "status" => "required",
            ]);

            ExpenseType::where("id", $id)->update([
                "description" => $request->description,
                "debit_id" => $request->debit_id,
                "credit_id" => $request->credit_id,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Expense type updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
