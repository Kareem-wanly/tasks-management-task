<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user on the users page: who, whether they can sign in, and their roles
 * as objects (UserResource gives role names instead).
 *
 * @property User $resource
 */
final class UserListItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'position' => $user->position ?: null,
            'is_active' => $user->is_active,
            'roles' => RoleRefResource::collection($this->whenLoaded('roles')),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
