<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\Sizes;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;

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
        try{
            $sizes = Sizes::all();

            return $this -> sendResponse($sizes);
        }catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }

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

