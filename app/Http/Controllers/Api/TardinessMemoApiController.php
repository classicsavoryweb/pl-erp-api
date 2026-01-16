<?php

namespace App\Http\Controllers\Api;

use App\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TardinessMemoApiController extends ApiBaseController
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "employee_number" => "required",
            "type" => "required",
            "subject" => "required",
            "memo_file" => "required|file",
        ]);

        if ($validator->fails()) {
            return $this->sendError(["message" => $validator->errors()]);
        }

        $employeeNumber = $request->input("employee_number");
        $user = User::where("employee_number", $employeeNumber)->first();
        if (!$user) {
            return $this->sendError(["message" => "Employee not found."]);
        }

        $baseUrl = env("CS_HUB_URL_LINK");
        $apiUser = env("CS_HUB_ADMIN_EMAIL");
        $apiPassword = env("CS_HUB_ADMIN_PASSWORD");

        if (!$baseUrl || !$apiUser || !$apiPassword) {
            return $this->sendError(["message" => "Savory HRIS connection is not configured."], 501);
        }

        $memoFile = $request->file("memo_file");
        if (!$memoFile || !$memoFile->isValid()) {
            return $this->sendError(["message" => "Invalid memo file."]);
        }

        $senderName = $request->input("sender_name", "Human Resources Department");
        $message = $request->input("message");

        try {
            $baseUrl = rtrim($baseUrl, "/");
            $endpoint = preg_match("#/api$#i", $baseUrl) ? $baseUrl : $baseUrl . "/api";
            $endpoint .= "/tardiness-memo";

            $response = Http::withBasicAuth($apiUser, $apiPassword)
                ->timeout(60)
                ->attach(
                    "memo_file",
                    file_get_contents($memoFile->getRealPath()),
                    $memoFile->getClientOriginalName()
                )
                ->post($endpoint, [
                    "employee_number" => $employeeNumber,
                    "type" => $request->input("type"),
                    "subject" => $request->input("subject"),
                    "message" => $message,
                    "sender_name" => $senderName,
                ]);

            if (!$response->successful()) {
                Log::warning("Savory HRIS memo API failed.", [
                    "employee_number" => $employeeNumber,
                    "status" => $response->status(),
                    "response" => $response->body(),
                ]);

                return $this->sendError([
                    "message" => "Memo creation failed in Savory HRIS.",
                    "status" => $response->status(),
                ], 502);
            }

            return $this->sendResponse([
                "message" => "Memo submitted successfully.",
                "remote_response" => $response->json(),
            ]);
        } catch (Exception $e) {
            Log::warning("Savory HRIS memo API error.", [
                "employee_number" => $employeeNumber,
                "error" => $e->getMessage(),
            ]);

            return $this->sendError(["message" => "Unable to submit memo."], 502);
        }
    }
}
