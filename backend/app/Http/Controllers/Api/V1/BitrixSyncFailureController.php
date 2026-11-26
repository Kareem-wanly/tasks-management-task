<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DismissBitrixSyncFailureRequest;
use App\Http\Requests\ListBitrixSyncFailuresRequest;
use App\Http\Requests\RetryBitrixSyncFailureRequest;
use App\Http\Resources\BitrixSyncFailureResource;
use App\Models\BitrixSyncFailure;
use App\Services\Contracts\BitrixSyncFailureServiceInterface;
use App\Services\Contracts\BitrixSyncStatusServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Step 5.2: the dead letters of the sync status page. */
final class BitrixSyncFailureController extends Controller
{
    public function __construct(
        private readonly BitrixSyncFailureServiceInterface $failures,
        private readonly BitrixSyncStatusServiceInterface $sync,
    ) {}

    public function index(ListBitrixSyncFailuresRequest $request): AnonymousResourceCollection
    {
        return BitrixSyncFailureResource::collection($this->failures->open());
    }

    public function retry(RetryBitrixSyncFailureRequest $request, BitrixSyncFailure $failure): JsonResponse
    {
        return (new BitrixSyncFailureResource($this->sync->retry($failure)))->response()->setStatusCode(202);
    }

    public function destroy(DismissBitrixSyncFailureRequest $request, BitrixSyncFailure $failure): JsonResponse
    {
        $this->failures->dismiss($failure);

        return response()->json(['message' => 'Failure dismissed.']);
    }
}
