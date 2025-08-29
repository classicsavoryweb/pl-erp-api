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

            $accounts = Account::select('acc_no', 'acc_name')
            ->where('acc_status', 'Active')
            ->groupBy('acc_name', 'acc_no')
            ->orderBy('acc_name')
            ->get();

            return $this->sendResponse(["accounts" => $accounts]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
