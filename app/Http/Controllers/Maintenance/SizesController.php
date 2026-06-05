<?php

namespace App\Http\Controllers\Maintenance;

use App\Models\sizes;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;

class SizesController extends Controller
{
    public function createUniform(Request $request){
        try {
            $request->validate([
                "description" => "required|unique:sizes,description",
                "status" => "required",
            ]);

            sizes::create([
                "description" => $request->description,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Uniform Created Successfully"]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getUniform(Request $request){
        try{
            $sizes = sizes::all();

            return $this -> sendResponse($sizes);
        }catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }

    }

    public function editUniform(Request $request , $id){
        try{
             $request->validate([
                "description" => "required|unique:sizes,description," . $id,
                "status" => "required",
            ]);
            sizes::where("id", $id)->update([
                "description" => $request->description,
                "status" => $request->status,
            ]);

            return $this->sendResponse(["message" => "Reason updated succesfully"]);
        }catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function deleteUniform($id){
        try {
            $sizes = sizes::find($id);
            $sizes->delete();

            return $this->sendResponse(["message"=> "Role and its permissions deleted successfully."]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }

    }
}

