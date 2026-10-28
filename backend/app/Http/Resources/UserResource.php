<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
{
    $permissionsLoader = function () {
        if (method_exists($this->resource, 'permissionNames')) {
            return $this->resource->permissionNames()->values();
        }
        if (method_exists($this->resource, 'getAllPermissions')) {
            return $this->resource->getAllPermissions()->pluck('name')->values();
        }
        return [];
    };

    $isAuthEndpoint = $request->is('api/me', 'api/login', 'api/register') 
        || $request->is('me', 'login', 'register');

    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'roles' => $this->whenLoaded('roles', function () {
            return $this->roles->pluck('name');
        }),

        'permissions' => $this->when($isAuthEndpoint, $permissionsLoader),
        'effective_permissions' => $this->when($isAuthEndpoint, $permissionsLoader),

        'created_at' => $this->created_at?->toISOString(),
        'updated_at' => $this->updated_at?->toISOString(),
    ];
}
}
