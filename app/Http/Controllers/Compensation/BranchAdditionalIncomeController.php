<?php

namespace App\Http\Controllers\Compensation;

use App\Http\Controllers\Controller;
use App\Imports\BranchAdditionalIncomeImport;
use App\Models\Branch;
use App\Models\BranchAdditionalIncome;
use App\Models\Cutoff;
use App\Exports\Compensation\BranchAdditionalIncomeExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Exception;
use DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Pagination\LengthAwarePaginator;

class BranchAdditionalIncomeController extends Controller
{
    public function getAllBranchAdditionalIncome(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;
            $payDate = $this->resolvePayDateFromCutoff($request);

            $branches = Branch::query()
                ->where("branches.status", "active");

            if ($request->filled("branch_id")) {
                $branches = $branches->where("branches.id", $request->branch_id);
            }

            if ($payDate) {
                $branches = $branches
                    ->leftJoin("branch_additional_income as bai", function ($join) use ($payDate) {
                        $join->on("bai.branch_id", "=", "branches.id")
                            ->where("bai.pay_date", "=", $payDate);
                    })
                    ->select(
                        "branches.id",
                        "branches.name",
                        DB::raw("COALESCE(bai.amount, 0) as amount")
                    );
            } else {
                $branches = $branches
                    ->select(
                        "branches.id",
                        "branches.name",
                        DB::raw("0 as amount")
                    );
            }

            $branches = $branches
                ->orderBy("branches.name", "ASC")
                ->paginate($perPage);

            $branches->setCollection(
                $branches->getCollection()->map(function ($row) use ($payDate) {
                    return [
                        "branch" => [
                            "id" => $row->id,
                            "name" => $row->name
                        ],
                        "amount" => (float) $row->amount,
                        "pay_date" => $payDate
                    ];
                })
            );

            return $this->sendResponse($branches);
        } catch (Exception $e) {
            return $this->sendError([
                "message" => $e->getMessage()
            ]);
        }
    }

    public function updateOrCreateIncome(Request $request) {
        try {
            DB::beginTransaction();

            $request->validate([
                "branch_id" => "required",
                "amount" => "required"
            ]);

            $payDate = $this->resolvePayDateFromCutoff($request);

            if (!$payDate) {
                DB::rollBack();
                return $this->sendError(["message" => "Cutoff not found."]);
            }

            BranchAdditionalIncome::updateOrCreate([
                "branch_id" => $request->branch_id,
                "pay_date" => $payDate
            ],[
                "amount" => $request->amount
            ]);

            DB::commit();
            return $this->sendResponse(["message" => "Additional income saved successfully"]);

        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function exportBranchAdditionalIncome(Request $request)
    {
        try {
            $payDate = $this->resolvePayDateFromCutoff($request);
            $rows = $this->buildBranchAdditionalIncomeRows($request, $payDate);

            return Excel::download(
                new BranchAdditionalIncomeExport($rows),
                "branch-additional-income.xlsx"
            );
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function importBranchAdditionalIncome(Request $request)
    {
        try {
            $request->validate([
                "additional_income_file" => "required|file"
            ]);

            $import = new BranchAdditionalIncomeImport();
            Excel::import($import, $request->file("additional_income_file"));

            $rows = $import->rows ?? collect();

            if ($rows->isEmpty()) {
                return $this->sendError(["message" => "No rows found in the uploaded file."]);
            }

            $branches = Branch::all();
            $branchesByNumber = $branches->keyBy(fn ($branch) => $this->normalizeText($branch->branch_number));

            $errors = [];
            $payload = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $rowData = $row instanceof Collection ? $row->toArray() : (array) $row;
                $hasValues = collect($rowData)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();

                if (!$hasValues) {
                    continue;
                }

                $branchNumberRaw = $this->getRowValue($rowData, [
                    "branch_number",
                    "branch_no",
                    "branchnumber",
                    "branch_no."
                ]);
                $branchNameRaw = $this->getRowValue($rowData, [
                    "branch_name",
                    "branch_nm",
                    "branchname"
                ]);
                $amountRaw = $this->getRowValue($rowData, ["amount", "amt", "value"]);
                $payDateRaw = $this->getRowValue($rowData, [
                    "payment_date",
                    "pay_date",
                    "paymentdate",
                    "date"
                ]);

                if ($branchNumberRaw === null || trim((string) $branchNumberRaw) === '') {
                    $errors[] = "Row {$rowNumber}: Branch Number is required.";
                    continue;
                }

                $normalizedBranchNumber = $this->normalizeText($branchNumberRaw);

                $branchByNumber = $branchesByNumber->get($normalizedBranchNumber);

                if (!$branchByNumber) {
                    $errors[] = "Row {$rowNumber}: Branch Number not found.";
                    continue;
                }

                $amountValue = $this->normalizeNumber($amountRaw);
                if ($amountValue === null) {
                    $errors[] = "Row {$rowNumber}: Amount is required or invalid.";
                    continue;
                }

                $payDate = $this->parseImportDate($payDateRaw);
                if (!$payDate) {
                    $errors[] = "Row {$rowNumber}: Payment Date is required or invalid.";
                    continue;
                }

                $cutoffForPayDate = Cutoff::where("pay_date", $payDate)->first();
                if (!$cutoffForPayDate) {
                    $errors[] = "Row {$rowNumber}: Invalid Payment Date.";
                    continue;
                }

                $payload[] = [
                    "branch_id" => $branchByNumber->id,
                    "amount" => round($amountValue, 2),
                    "pay_date" => $payDate
                ];
            }

            if (!empty($errors)) {
                return $this->sendError([
                    "message" => "Import failed. Fix the errors and try again.",
                    "errors" => $errors
                ]);
            }

            DB::beginTransaction();

            foreach ($payload as $item) {
                BranchAdditionalIncome::updateOrCreate([
                    "branch_id" => $item["branch_id"],
                    "pay_date" => $item["pay_date"]
                ], [
                    "amount" => $item["amount"]
                ]);
            }

            DB::commit();

            return $this->sendResponse(["message" => "Additional income imported successfully."]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    private function getRowValue(array $row, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    private function normalizeNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = str_replace([",", " "], "", (string) $value);
        if ($cleaned === '' || !is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }

    private function parseImportDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format("Y-m-d");
            }

            return Carbon::parse($value)->format("Y-m-d");
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeText($value): string
    {
        if ($value === null) {
            return '';
        }

        $text = (string) $value;
        // Replace non-breaking spaces and collapse whitespace.
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return strtolower(trim($text));
    }

    private function buildBranchAdditionalIncomeRows(Request $request, ?string $payDate): Collection
    {
        $branches = Branch::query()
            ->where("branches.status", "active");

        if ($request->filled("branch_id")) {
            $branches = $branches->where("branches.id", $request->branch_id);
        }

        if ($payDate) {
            $branches = $branches
                ->leftJoin("branch_additional_income as bai", function ($join) use ($payDate) {
                    $join->on("bai.branch_id", "=", "branches.id")
                        ->where("bai.pay_date", "=", $payDate);
                })
                ->select(
                    "branches.id",
                    "branches.branch_number",
                    "branches.name",
                    DB::raw("COALESCE(bai.amount, 0) as amount")
                );
        } else {
            $branches = $branches->select(
                "branches.id",
                "branches.branch_number",
                "branches.name",
                DB::raw("0 as amount")
            );
        }

        return $branches->orderBy("branches.name", "ASC")
            ->get()
            ->map(function ($row) use ($payDate) {
                return [
                    "branch_number" => $row->branch_number,
                    "branch_name" => $row->name,
                    "amount" => (float) $row->amount,
                    "pay_date" => $payDate ?? "",
                ];
            });
    }

    private function hasCutoffSelection(Request $request): bool
    {
        if ($request->filled("cutoff_id")) {
            return true;
        }

        return $request->filled("cutoff_start")
            && $request->filled("cutoff_end")
            && $request->filled("year");
    }

    private function resolvePayDateFromCutoff(Request $request): ?string
    {
        if ($request->filled("cutoff_id")) {
            $cutoff = Cutoff::find($request->cutoff_id);
            return $cutoff?->pay_date;
        }

        if ($this->hasCutoffSelection($request)) {
            $dates = $this->normalizeCutOff($request->cutoff_start, $request->cutoff_end, $request->year);
            $cutOffKey = $dates["start_date"] . "-" . $dates["end_date"];
            $cutoff = Cutoff::where("cut_off", $cutOffKey)->first();
            return $cutoff?->pay_date;
        }

        $activeCutoff = Cutoff::where("status", "active")->first();
        return $activeCutoff?->pay_date;
    }
}
