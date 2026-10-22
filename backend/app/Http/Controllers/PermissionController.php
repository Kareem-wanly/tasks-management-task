<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $permissions = Permission::all(['id', 'name', 'display_name', 'description']);

        return response()->json([
            'data' => $permissions,
        ]);
    }
}
