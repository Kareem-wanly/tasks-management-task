<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A project member: who they are and their role in the project (owner,
 * moderator or member), read from the membership row.
 *
 * @property User $resource
 */
final class ProjectMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $membership = $this->resource->getRelationValue('pivot');

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $membership instanceof Model ? $membership->getAttribute('role') : null,
        ];
    }
}
