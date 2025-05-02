<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\TrainingTitle;
use Illuminate\Http\Request;
use Exception;

class TrainingTitleController extends Controller
{
    public function getAllTrainingTitles(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $trainingTitles = TrainingTitle::paginate($perPage);
            return $this->sendResponse($trainingTitles);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function getTrainingTitles(Request $request) {
        try {
            $trainingTitles = TrainingTitle::all();
            return $this->sendResponse(["training_titles" => $trainingTitles]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function createTrainingTitle(Request $request) {
        try {
            $request->validate([
                "option" => "required",
                "title" => "required",
                "venue" => "required",
                "speaker" => "required",
                "status" => "required",
            ]);

            TrainingTitle::create([
                "option" => $request->option,
                "title" => $request->title,
                "venue" => $request->venue,
                "speaker" => $request->speaker,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Training title created successfully"]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function editTrainingTitle(Request $request, $id) {
        try {
            $request->validate([
                "option" => "required",
                "title" => "required",
                "venue" => "required",
                "speaker" => "required",
                "status" => "required",
            ]);

            TrainingTitle::where("id", $id)->update([
                "option" => $request->option,
                "title" => $request->title,
                "venue" => $request->venue,
                "speaker" => $request->speaker,
                "status" => $request->status,
            ]);
            return $this->sendResponse(["message" => "Training title updated successfully"]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }
}
