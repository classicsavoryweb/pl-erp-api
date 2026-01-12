<?php

namespace App\Http\Controllers\Admin;

use App\Exports\Administrator\AdminuserExports;
use App\Http\Controllers\Controller;
use App\Models\User;
use Hash;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    public function getAllUsers (Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $users = User::with("role");

            if ($request->has(["column_type", "key_word"])) {
                $column = $request->column_type;
                $keyword = $request->key_word;

                if ($column == "full_name") {
                    $users = $users->where(function ($query) use ($keyword) {
                        $query->whereAny(
                            ["first_name", "last_name", "middle_name"],
                            'LIKE',
                            "%$keyword%"
                        )->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(' ', first_name, last_name)"), 'LIKE', "%$keyword%");
                            }
                        )->orWhere(function ($query) use ($keyword) {
                                $query->where(DB::raw("CONCAT_WS(', ', last_name, first_name)"), 'LIKE', "%$keyword%");
                            }
                        );
                    });
                } else {
                    if (Str::contains($column, '.')) {
                        [$relation, $relatedColumn] = explode('.', $column, 2);

                        $users = $users->whereHas($relation, function ($query) use ($relatedColumn, $keyword) {
                            $query->where($relatedColumn, 'LIKE', "%$keyword%");
                        });
                    } else {
                        $users = $users->where($column, 'LIKE', "%$keyword%");
                    }
                }
            }

            if ($request->has(["status"])) {
                $users = $users->where("status", $request->status);
            }

            if ($request->has(["export"]) && $request->export) {
                $users = $users->get();
                return Excel::download(new AdminuserExports($users), "users.xls");
            }

            $users = $users->paginate($perPage);
            return $this->sendResponse($users);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function exportUsers (Request $request) {
        $request["export"] = true;

        return $this->getAllUsers($request);
    }
    
    public function editUser (Request $request, $id) {
        try {
            $user = User::find($id);
            
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->email = $request->email;
            $user->status = $request->status;
            $user->role_id = $request->role_id;
            $user->employee_number = $request->employee_number;
            $user->middle_name = $request->middle_name ?? null;
            if ($request->password) {
                $user->password = Hash::make(value: $request->password);
            }

            $user->save();
            return $this->sendResponse([
                "message"=> "User updated successfully.",
            ]);
        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
