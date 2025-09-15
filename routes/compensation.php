<?php

use App\Http\Controllers\Compensation\CutOffController;
use Illuminate\Support\Facades\Route;

//compensation routes
Route::middleware('auth:sanctum')->group( function () {

    //cutoff
    Route::get("/get-all-cut-off",[CutOffController::class, "getAllCutOffs"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->post("/create-cut-off", [CutOffController::class, "createCutOff"]);
    Route::middleware("permission:perform_compensation_cutoff_setup")->post("/edit-cut-off/{id}", [CutOffController::class, "editCutOff"]);
});
