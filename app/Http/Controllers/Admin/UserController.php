<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Hash;
use Illuminate\Http\Request;
use Exception;

class UserController extends Controller
{
    public function getAllUsers (Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $users = User::with("role")->paginate($perPage);

            return $this->sendResponse($users);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
    
    public function editUser (Request $request, $id) {
        try {
            $user = User::find($id);
            
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->email = $request->email;
            $user->status = $request->status;
            $user->role_id = $request->role_id;
            $user->middle_name = $request->middle_name ?? null;
            if ($request->password) {
                $user->password = Hash::make($request->password);
            }

            $user->save();
            return $this->sendResponse([
                "message"=> "User updated successfully.",
            ]);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
