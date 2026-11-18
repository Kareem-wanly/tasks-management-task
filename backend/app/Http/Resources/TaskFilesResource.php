<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\TaskFiles;
use App\Http\Requests\UploadTaskFileRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.5: { data: files, meta: { stale, can_manage, max_kb } }.
 *
 * @property TaskFiles $resource
 */
class TaskFilesResource extends JsonResource
{
    public static $wrap;

    public function toArray(Request $request): array
    {
        return [
            'data' => TaskFileResource::collection($this->resource->files),
            'meta' => [
                'stale' => $this->resource->stale,
                'can_manage' => $request->user()->can('manageFiles', $this->resource->task),
                'max_kb' => UploadTaskFileRequest::MAX_KB,
            ],
        ];
    }
}
