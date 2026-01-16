<?php

namespace App\Http\Controllers\Api;

use App\AdminAnnoucementMemosAttachment;
use App\AdminAnnoucementMemosModel;
use App\AnnouncementMemosReceiptBuildFilesModel;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Exception;

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

        $recipient = AnnouncementMemosReceiptBuildFilesModel::where("source_recipients", "Employee")
            ->where("status", "active")
            ->orderBy("id")
            ->first();

        if (!$recipient) {
            return $this->sendError(["message" => "Memo recipient build file not configured."]);
        }

        $senderName = $request->input("sender_name", "Auditing Department");
        $message = $request->input("message");
        $memoFile = $request->file("memo_file");

        DB::beginTransaction();
        try {
            $now = Carbon::now();

            $memo = new AdminAnnoucementMemosModel();
            $memo->category = "Memo";
            $memo->type = $request->input("type");
            $memo->date_created = $now->toDateString();
            $memo->subject = $request->input("subject");
            $memo->current_branch_of_sender = $senderName;
            $memo->name_of_sender = $senderName;
            $memo->if_required_reply_tab = "NO";
            $memo->req_attachment = "YES";
            $memo->auto_unpublish = "NO";
            $memo->message = $message;
            $memo->if_attachment = "yes";
            $memo->publish_recipient = $recipient->description;
            $memo->publish_recipient_id = $recipient->id;
            $memo->publish_date = $now->toDateString();
            $memo->publish_addressee = $user->id;
            $memo->publish_cc_to = "Employee";
            $memo->source = "Employee";
            $memo->status = "Published";
            $memo->created_by_date = $now;
            $memo->published_by_date = $now;
            $memo->save();

            DB::table("annoucement_memo_pivot_recipient_published_models")->insert([
                "annoucement_memos_id" => $memo->id,
                "annoucement_memos_recipient_id" => $recipient->id,
                "source_id" => $user->id,
                "source" => "Employee",
                "created_at" => $now,
                "updated_at" => $now,
            ]);

            $path = public_path("annoucement_memo_attachment");
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }

            $safeName = preg_replace("/[^A-Za-z0-9._-]/", "_", $memoFile->getClientOriginalName());
            $fileName = Str::random(6) . "-" . $safeName;
            $memoFile->move($path, $fileName);

            AdminAnnoucementMemosAttachment::create([
                "annoucement_memo_id" => $memo->id,
                "attachment" => $fileName,
                "created_at" => $now,
                "updated_at" => $now,
            ]);

            DB::commit();

            return $this->sendResponse([
                "message" => "Memo created successfully.",
                "memo_id" => $memo->id,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
