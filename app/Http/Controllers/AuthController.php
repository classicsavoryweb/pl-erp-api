<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function login(Request $request) {
        if (Auth::attempt([
            "email" => $request->email,
            "password" => $request->password
        ])) {
            $user = Auth::user();

            if (!$user->isActive()) {
                return $this->sendError(["message" => "Your account is inactive"]);
            }

            $role = Role::find($user->role_id);
            $user->assignRole($role);
            $token = $user->createToken($user->email, $role->permissions->pluck("name")->toArray());

            activity()->causedBy($user)->useLog("Login")->log('User logged in');

            return $this->sendResponse([
                "message" => "Logged in successfully",
                "user" => $user,
                "token" => $token->plainTextToken,
                "permissions" => $role->permissions->pluck("name")->toArray()
            ]);

        } else {
            return $this->sendError(["message" => "Invalid credentials" ]);
        }
    }

    public function logout(Request $request) {
        activity()->causedBy(Auth::user())->useLog("Logout")->log('User logged out');
        Auth::user()->tokens()->delete();
        return $this->sendResponse([
            "message" => "Logged out successfully",
        ]);
    }

    public function createUser(Request $request) {
        try {
            $request->validate([
                "first_name" => "required",
                "last_name" => "required",
                "role_id" => "required",
                "employee_number" => "required|unique:users",
                "email" => "required|unique:users",
                "password" => "required",
            ]);

            User::create([
                "first_name" => $request->first_name,
                "last_name" => $request->last_name,
                "middle_name" => $request->middle_name,
                "role_id" => $request->role_id,
                "employee_number" => $request->employee_number,
                "email"=> $request->email,
                "password"=> bcrypt($request->password),
                "status" => "active"
            ]);

            return $this->sendResponse([
                "message"=> "User created successfully.",
            ]);

        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }    
    }

    public function changePassword(Request $request) {
        try {
            $request->validate([
                "old_password"=> "required",
                "new_password"=> "required",
                "confirm_password"=> "required",
            ]);

            if (Hash::check($request->old_password, Auth::user()->password)) {
                Auth::user()->update([
                    "password"=> bcrypt($request->new_password)
                ]);
            } else {
                return $this->sendError(["old_password" => "Old password is incorrect." ]);    
            }

        } catch (Exception $e) {
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }
}
