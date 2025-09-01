<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use Illuminate\Http\Request;
use Exception;

class AccountController extends Controller
{

    public function getAccounts(Request $request) {
        try {
            $accounts = Account::select('acc_no', 'acc_name');

            if ($request->has("keyWord")) {
                $keyword = $request->keyWord;
                $accounts = $accounts->where("acc_name", "LIKE", "%$keyword%");
            }

            if ($request->has("status") && in_array($request->status, ['active', 'inactive'])) {
                $accounts = $accounts->where('acc_status', ucfirst($request->status));
            }

            $accounts = $accounts
                ->groupBy('acc_name', 'acc_no')
                ->orderBy('acc_name')
                ->get();

            return $this->sendResponse(["accounts" => $accounts]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
