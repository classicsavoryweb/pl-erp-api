<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Offense;
use App\Models\Sanction;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Exception;

class OffenseController extends Controller
{
    public function getAllOffenses(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $offenses = Offense::query();
            if ($request->has(["column_type", "key_word"])) {
               $offenses = $offenses->where($request->column_type,"LIKE","%".$request->key_word."%");
            }

            if ($request->has(["status"])) {
                $offenses = $offenses->where("status", $request->status);
            }
            $offenses = $offenses->paginate($perPage);
            return $this->sendResponse($offenses);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getOffenses(Request $request) {
        try {
            $offenses = Offense::where("status", "active")->get();

            return $this->sendResponse([ "offenses" => $offenses]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createOffense(Request $request) {
        try {
            $request->validate([
                "category" => "required",
                "subcategory" => "required",
                "description" => "required|unique:offenses",
                "status" => "required",
            ]);

            Offense::create([
                "category"=> $request->category,
                "subcategory" => $request->subcategory,
                "description" => $request->description,
                "status" => $request->status,
            ]);

            return $this->sendResponse([ "message" => "Offense created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editOffense(Request $request, $id) {
        try {
            $request->validate([
                "category" => "required",
                "subcategory" => "required",
                "description" => "required|unique:offenses,description,except,".$id,
                "status" => "required",
            ]);

            Offense::where("id", $id)->update([
                "category"=> $request->category,
                "subcategory" => $request->subcategory,
                "description" => $request->description,
                "status" => $request->status,
            ]);

            return $this->sendResponse([ "message" => "Offense updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getAllCategories(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $categories = Category::paginate($perPage);
            return $this->sendResponse($categories);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getCategories(Request $request) {
        try {

            $categories = Category::where("status", "active")->get();
            return $this->sendResponse(["categories" => $categories]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createCategory(Request $request) {
        try {
            $request->validate([
                "name" => "required",
                "status" => "required",
            ]);

            Category::create([
                "name"=> $request->name,
                "status" => $request->status,
            ]);

            return $this->sendResponse([ "message" => "Category created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editCategory(Request $request, $id) {
        try {
            $request->validate([
                "name" => "required",
                "status" => "required",
            ]);

            Category::where("id", $id)->update([
                "name"=> $request->name,
                "status" => $request->status,
            ]);

            return $this->sendResponse([ "message" => "Category updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getSubCategories(Request $request) {
        try {

            $categories = SubCategory::where("status", "active")->get();
            return $this->sendResponse(["categories" => $categories]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getAllSubCategories(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $subCategories = SubCategory::paginate($perPage);
            return $this->sendResponse($subCategories);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function createSubCategory(Request $request) {
        try {
            $request->validate([
                "name" => "required",
                "status" => "required",
            ]);

            SubCategory::create([
                "name"=> $request->name,
                "status" => $request->status,
            ]);

            return $this->sendResponse([ "message" => "Sub category created successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function editSubCategory(Request $request, $id) {
        try {
            $request->validate([
                "name" => "required",
                "status" => "required",
            ]);

            SubCategory::where("id", $id)->update([
                "name"=> $request->name,
                "status" => $request->status,
            ]);

            return $this->sendResponse([ "message" => "Sub category updated successfully"]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getSanctions(Request $request) {
        try {
            $sanctions = Sanction::all();
            return $this->sendResponse([ "sanctions" => $sanctions]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

}
