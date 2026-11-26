<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TaskPriority;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of /task-priorities.
 *
 * @property TaskPriority $resource
 */
final class TaskPriorityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lookup = $this->resource;

        return [
            'id' => $lookup->id,
            'key' => $lookup->key,
            'name' => $lookup->name,
            'bitrix_id' => $lookup->bitrix_id,
            'is_system' => $lookup->is_system,
            'sort' => $lookup->sort,
            'color' => $lookup->color,
        ];
    }
}
