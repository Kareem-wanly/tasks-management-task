<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BitrixEventRequest;
use App\Services\Contracts\BitrixEventServiceInterface;
use Illuminate\Http\JsonResponse;

/**
 * Step 3.4: Bitrix24 calls this for every bound event. We queue the work
 * and answer at once.
 */
final class BitrixEventController extends Controller
{
    public function __construct(private readonly BitrixEventServiceInterface $events) {}

    public function handle(BitrixEventRequest $request): JsonResponse
    {
        $this->events->handle($request->toDto());

        return response()->json(['ok' => true]);
    }
}
