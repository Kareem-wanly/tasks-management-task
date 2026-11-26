<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListPermissionsRequest;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function index(ListPermissionsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->permissions->list()]);
    }
}
