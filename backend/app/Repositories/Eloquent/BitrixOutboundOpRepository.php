<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\BitrixOutboundOp;
use App\Models\User;
use App\Repositories\Contracts\BitrixOutboundOpRepositoryInterface;
use Illuminate\Support\Collection;

class BitrixOutboundOpRepository implements BitrixOutboundOpRepositoryInterface
{
    public function record(string $entity, int $bitrixId, string $action, ?string $fingerprint, User $actor): void
    {
        BitrixOutboundOp::create([
            'entity' => $entity,
            'bitrix_id' => $bitrixId,
            'action' => $action,
            'fingerprint' => $fingerprint,
            'status' => BitrixOutboundOp::SENT,
            'user_id' => $actor->id,
        ]);
    }

    public function pending(string $entity, int $bitrixId): Collection
    {
        return BitrixOutboundOp::where('entity', $entity)
            ->where('bitrix_id', $bitrixId)
            ->where('status', BitrixOutboundOp::SENT)
            ->where('created_at', '>=', now()->subSeconds(BitrixOutboundOp::WINDOW_SECONDS))
            ->latest('id')
            ->get();
    }

    public function settle(BitrixOutboundOp $op, string $status): void
    {
        BitrixOutboundOp::where('entity', $op->entity)
            ->where('bitrix_id', $op->bitrix_id)
            ->where('status', BitrixOutboundOp::SENT)
            ->where('id', '<=', $op->id)
            ->update(['status' => $status, 'updated_at' => now()]);
        $op->status = $status;
    }
}
