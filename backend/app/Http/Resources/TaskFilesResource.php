<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Task\TaskFilesDTO;
use App\Http\Requests\UploadTaskFileRequest;
use App\Http\Resources\Concerns\ResolvesViewer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Step 4.5: { data: files, meta: { stale, can_manage, max_kb } }.
 *
 * @property TaskFilesDTO $resource
 */
final class TaskFilesResource extends JsonResource
{
    use ResolvesViewer;

    public static $wrap;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => TaskFileResource::collection($this->resource->files),
            'meta' => [
                'stale' => $this->resource->stale,
                'can_manage' => $this->viewer($request)->can('manageFiles', $this->resource->task),
                'max_kb' => UploadTaskFileRequest::MAX_KB,
            ],
        ];
    }
}
