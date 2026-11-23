<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class Controller
{
    /**
     * The { success, message, data, meta } shape some list endpoints answer with.
     *
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $page
     * @param  class-string<JsonResource>  $resource  Shapes each item.
     */
    protected function paginated(LengthAwarePaginator $page, string $resource, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
