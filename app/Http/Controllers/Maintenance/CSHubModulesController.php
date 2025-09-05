<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\CSHubModulesExport;
use App\Http\Controllers\Controller;
use App\Models\CSHubModule;
use Exception;
use Illuminate\Http\Request;

class CSHubModulesController extends Controller
{

    public function getCsHubModules(Request $request)
    {
        try {
            $modules = CSHubModule::where("status", "active")->get();
            return $this->sendResponse(["modules" => $modules]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
