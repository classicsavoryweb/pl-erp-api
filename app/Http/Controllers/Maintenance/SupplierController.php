<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Supplier;
use App\Exports\Maintenance\SupplierExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class SupplierController extends Controller
{
     public function createUniformSupplier(Request $request){
        try {
            $request->validate([
                "vendor_number" => "required|unique:suppliers,vendor_number",
                "vendor_name" => "required|unique:suppliers,vendor_name",
            ]);

            Supplier::create([
                "vendor_number" => $request->vendor_number,
                "vendor_name" => $request->vendor_name,
            ]);

            return $this->sendResponse(["message" => "Supplier Addedd Successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getUniformSupplier(Request $request){
         try {
            $perPage = $request->per_page ?? 10;

            $suppliers = Supplier::query();
            if ($request->has(["column_type", "key_word"])) {
               $suppliers = $suppliers->where($request->column_type,"LIKE","%".$request->key_word."%");
            }
           
            if ($request->has(["export"]) && $request->export) {
                $suppliers = $suppliers->get();
                return Excel::download(new SupplierExport($suppliers), "suppliers.xls");
            }

            $suppliers = $suppliers->paginate($perPage);
            return $this->sendResponse($suppliers);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }

    }
    public function exportSupplier (Request $request) {
        $request["export"] = true;

        return $this->getUniformSupplier($request);
    }

    public function editUniformSupplier(Request $request , $id){
        try{
             $request->validate([
                "vendor_number" => "required|unique:uniform_supplier,vendor_number," . $id,
                "vendor_name" => "required|unique:Supplier,vendor_number",
            ]);
            Supplier::where("id", $id)->update([
                "vendor_name" => $request->vendor_name,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Reason updated succesfully"]);
        }catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
    public function exportAllPositions (Request $request) {
        $request["export"] = true;

        return $this->getAllPositions($request);
    }

   
}
