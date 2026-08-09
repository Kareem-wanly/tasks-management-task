<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::all(['id', 'name', 'description']);

        return response()->json([
            'permissions' => $permissions,
        ]);
    }
}