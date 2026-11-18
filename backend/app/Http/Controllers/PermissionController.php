<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListPermissionsRequest;
use App\Services\Contracts\PermissionServiceInterface;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __construct(private readonly PermissionServiceInterface $permissions) {}

    public function index(ListPermissionsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->permissions->list()]);
    }
}
