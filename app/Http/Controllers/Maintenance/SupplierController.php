<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;

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
        $perPage = $request->per_page ?? 10;

        try{
            $suppliers = Supplier::paginate($perPage);
            return $this -> sendResponse($suppliers);
        }catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }

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

   
}
