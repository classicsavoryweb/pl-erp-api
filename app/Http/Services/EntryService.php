<?php
namespace App\Http\Services;

use App\Models\Branch;

class EntryService {

    public function checkBranchesWithEmptyAccounts(
        $branchIds = [],
        $accounts = [
            "ho_13th_debit",
            "ho_13th_debit_1",
            "ho_13th_credit",
            "branch_ho_13th_debit",
            "branch_ho_13th_debit_1",
            "branch_ho_13th_credit",
            "branch_ho_stat_debit",
            "branch_ho_stat_credit",
            "expense_debit",
            "expense_credit",
            "advance_debit",
            "advance_credit",
        ]
    ) {
        $branches = Branch::whereIn("id", $branchIds)
        ->where(function ($query) use ($accounts) {
            $arr = ["branch_ho_stat_debit", "branch_ho_stat_credit"];
            foreach ($accounts as $account) {
                $query->orWhere(function ($q) use ($account, $arr) {
                    $q->where(function ($sub) use ($account) {
                        $sub->whereNull($account)
                            ->orWhere($account, "=", "");
                    });
                    if (in_array($account, $arr)) {
                        $q->where("type", "Branch");
                    }
                });
            }
        })
        ->pluck("name")
        ->toArray();

        return $branches;
    }

    public function checkBrancesWithDifferentCompany($branchIds = []) {
        $company = env("COMPANY_NAME") === "SFI"
        ? "Savory Fast Food Inc."
        : "Tony Ting Food Concepts Inc.";

        $branches = Branch::whereIn("id", $branchIds)
        ->whereNot("company", $company)
        ->pluck("name")
        ->toArray();

        return $branches;
    }
}