<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Employee;
use App\Models\PettyCashCustodian;
use Exception;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Storage;
use Str;

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
            if ($role) {
                $user->syncRoles($role);
                $user["role_name"] = $role->name;
                $permissions = $role->permissions->pluck("name")->toArray();

                $isCustodian = PettyCashCustodian::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->exists();
                if ($isCustodian) {
                    $permissions[] = 'is_petty_cash_custodian';
                }

                $token = $user->createToken(
                    $user->email,
                    $permissions,
                    now()->addHours(12)
                );

                activity()->causedBy($user)->useLog("Login")->log('user logged in');

                return $this->sendResponse([
                    "message" => "Logged in successfully",
                    "user" => $user,
                    "token" => $token->plainTextToken,
                    "permissions" => $permissions
                ]);
            }

            return $this->sendError(["message" => "Role not existing" ]);

        } else {

            return $this->sendError(["message" => "Invalid credentials" ]);
        }
    }

    public function logout(Request $request) {
        activity()->causedBy(Auth::user())->useLog("Logout")->log('user logged out');
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

            $employeeNumber = $request->employee_number;
            $employeeNumberVariants = [$employeeNumber];
            $trimmed = ltrim($employeeNumber, '0');
            if ($trimmed === "") {
                $trimmed = "0";
            }
            if ($trimmed !== $employeeNumber) {
                $employeeNumberVariants[] = $trimmed;
            }
            if (Str::startsWith($employeeNumber, 'A')) {
                $suffix = substr($employeeNumber, 1);
                $trimmedSuffix = ltrim($suffix, '0');
                if ($trimmedSuffix === "") {
                    $trimmedSuffix = "0";
                }
                $employeeNumberVariants[] = "A" . $trimmedSuffix;
            }
            $employeeNumberVariants = array_values(array_unique($employeeNumberVariants));

            $employee = Employee::with("current_position")
                ->whereIn("employee_number", $employeeNumberVariants)
                ->first();
            $position = $employee?->current_position?->description;

            User::create([
                "first_name" => $request->first_name,
                "last_name" => $request->last_name,
                "middle_name" => $request->middle_name,
                "role_id" => $request->role_id,
                "employee_number" => $request->employee_number,
                "position" => $position,
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
