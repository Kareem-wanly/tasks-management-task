<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowBitrixSyncStatusRequest;
use App\Http\Requests\StartBitrixSyncRequest;
use App\Http\Resources\BitrixSyncStatusResource;
use App\Services\Contracts\BitrixSyncStatusServiceInterface;
use Illuminate\Http\JsonResponse;

/** Step 5.1: the sync status page. */
final class BitrixSyncController extends Controller
{
    public function __construct(private readonly BitrixSyncStatusServiceInterface $sync) {}

    public function show(ShowBitrixSyncStatusRequest $request): BitrixSyncStatusResource
    {
        return new BitrixSyncStatusResource($this->sync->status());
    }

    public function start(StartBitrixSyncRequest $request): JsonResponse
    {
        return (new BitrixSyncStatusResource($this->sync->start($request->toDto())))->response()->setStatusCode(202);
    }
}
