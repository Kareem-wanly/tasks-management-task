<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of /task-statuses.
 *
 * @property TaskStatus $resource
 */
final class TaskStatusResource extends JsonResource
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
            'is_final' => $lookup->is_final,
            'sort' => $lookup->sort,
            'color' => $lookup->color,
        ];
    }
}
