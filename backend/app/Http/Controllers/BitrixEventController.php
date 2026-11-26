<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BitrixEventRequest;
use App\Services\BitrixEventService;
use Illuminate\Http\JsonResponse;

/**
 * Step 3.4: Bitrix24 calls this for every bound event. We queue the work
 * and answer at once.
 */
class BitrixEventController extends Controller
{
    public function __construct(private readonly BitrixEventService $events) {}

    public function handle(BitrixEventRequest $request): JsonResponse
    {
        $this->events->handle((string) $request->input('event'), (array) $request->input('data', []));

        return response()->json(['ok' => true]);
    }
}
