<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionController extends Controller
{
    public function getAllRolesAndPermissions(Request $request) {
        try {
            $perPage = $request->per_page ?? 10;

            $roles = Role::with("permissions")->paginate($perPage);

            return $this->sendResponse($roles);
        } catch(Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function getAllPermissions (Request $request) {
        try {
            $permissions = Permission::all();

            return $this->sendResponse(["permissions" => $permissions]);
        } catch(Exception $e) {

            return $this->sendError($e->getMessage());
        }
    }

    public function updateOrCreateNewRole(Request $request) {
        try {
            $role = Role::updateOrCreate([
                "name" => $request->name,
                "guard_name" => "web",
            ]);

            $permissions = json_decode($request->permissions);
            $role->syncPermissions($permissions);

            return $this->sendResponse(["message"=> "Roles and permission saved successfully"]);
        } catch(Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
