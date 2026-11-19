<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'position' => $this->position ?: null,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),

            'effective_permissions' => $this->when(
                // Only the signed-in user's own account carries what they may do.
                $request->routeIs('api.v1.me.show', 'api.v1.auth.login', 'api.v1.auth.register'),
                fn () => method_exists($this->resource, 'permissionNames')
                    ? $this->resource->permissionNames()->values()
                    : []
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
