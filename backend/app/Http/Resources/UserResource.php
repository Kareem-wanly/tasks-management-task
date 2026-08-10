<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'email'                 => $this->email,
            'roles'                 => $this->whenLoaded('roles', function () {
            return $this->roles->pluck('name');
            }),
            'effective_permissions' => $this->when($this->relationLoaded('roles'), function () {
             return $this->getEffectivePermissions()->pluck('name');
            }),
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}