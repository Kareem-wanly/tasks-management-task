<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TaskChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskChecklistItem */
final class TaskChecklistItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            // Bitrix names lists made without a title BX_CHECKLIST_1, 2… and shows them as "Checklist 1".
            'title' => preg_replace('/^BX_CHECKLIST_(\d+)$/', 'Checklist $1', $this->title),
            'is_complete' => $this->is_complete,
            'sort_index' => $this->sort_index,
        ];
    }
}
