<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        
        if (!$request->user()->hasPermission('permissions.view') && !$request->user()->hasPermission('roles.manage')) {
            return response()->json([
                'message' => 'This action is unauthorized.'
            ], 403);
        }

        $permissions = Permission::all(['id', 'name', 'display_name', 'description']);

        return response()->json([
            'permissions' => $permissions,
        ]);
    }
}