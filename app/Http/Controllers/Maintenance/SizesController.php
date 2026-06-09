<?php

namespace App\Http\Controllers\Maintenance;

use App\Exports\Maintenance\SizesExport;
use App\Models\Sizes;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use Maatwebsite\Excel\Facades\Excel;

class SizesController extends Controller
{
    public function createUniformSizes(Request $request){
        try {
            $request->validate([
                "description" => "required|unique:sizes,description",
                "status" => "required",
            ]);

            Sizes::create([
                "description" => $request->description,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Uniform Created Successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getUniformSizes(Request $request){
       try {
            $perPage = $request->per_page ?? 10;

            $sizes = Sizes::query();
            if ($request->has(["column_type", "key_word"])) {
               $sizes = $sizes->where($request->column_type,"LIKE","%".$request->key_word."%");
            }
           
            if ($request->has(["export"]) && $request->export) {
                $sizes = $sizes->get();
                return Excel::download(new SizesExport($sizes), "sizes.xls");
            }

            $sizes = $sizes->paginate($perPage);
            return $this->sendResponse($sizes);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    
    }
    public function exportSizes (Request $request) {
        $request["export"] = true;

        return $this->getUniformSizes($request);
    }

    public function editUniformSizes(Request $request , $id){
        try{
             $request->validate([
                "description" => "required|unique:sizes,description," . $id,
                "status" => "required",
            ]);
            Sizes::where("id", $id)->update([
                "description" => $request->description,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Reason updated succesfully"]);
        }catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

}

