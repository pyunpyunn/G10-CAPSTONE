<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\Web\PersonnelRoleService;
use Illuminate\Http\Request;
class PersonnelRoleController extends Controller
{
    public function index(PersonnelRoleService $roles) { return response()->json(['data' => $roles->workspace()]); }
    public function store(Request $request, PersonnelRoleService $roles) { return response()->json(['data' => $roles->save($request)], 201); }
    public function update(Request $request, int $roleId, PersonnelRoleService $roles) { return response()->json(['data' => $roles->save($request, $roleId)]); }
    public function destroy(Request $request, int $roleId, PersonnelRoleService $roles) { return response()->json(['data' => $roles->delete($request, $roleId)]); }
}
