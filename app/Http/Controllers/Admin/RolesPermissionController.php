<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use DB;
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
            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getAllPermissions (Request $request) {
        try {
            $permissions = Permission::orderBy("module")
            ->orderBy("id")
            ->get();

            return $this->sendResponse(["permissions" => $permissions]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function getAllRoles (Request $request) {
        try {
            $roles = Role::all();

            return $this->sendResponse(["roles" => $roles]);
        } catch(Exception $e) {

            return $this->sendError(["message" => $e->getMessage() ]);
        }
    }

    public function updateOrCreateNewRole(Request $request) {
        try {
            DB::beginTransaction();
            $request->validate([
                "name" => "required",
                "permissions" => "required"
            ]);

            $role = Role::updateOrCreate([
                "name" => $request->name,
                "guard_name" => "web",
            ]);

            $role->syncPermissions($request->permissions);
            DB::commit();
            return $this->sendResponse(["message"=> "Role and its permissions saved successfully"]);
        } catch(Exception $e) {
            DB::rollBack();
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }

    public function deleteRolesAndPermissions(Request $request, $id) {
        try {
            $role = Role::find($id);
            $role->delete();

            return $this->sendResponse(["message"=> "Role and its permissions deleted successfully."]);
        } catch(Exception $e) {
            return $this->sendError(["message" => $e->getMessage()]);
        }
    }
}
