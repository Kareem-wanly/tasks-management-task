<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\OutboundOpStatus;
use App\Models\BitrixOutboundOp;
use App\Models\User;
use App\Repositories\Contracts\BitrixOutboundOpRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentBitrixOutboundOpRepository implements BitrixOutboundOpRepositoryInterface
{
    public function record(string $entity, int $bitrixId, string $action, ?string $fingerprint, User $actor): void
    {
        BitrixOutboundOp::create([
            'entity' => $entity,
            'bitrix_id' => $bitrixId,
            'action' => $action,
            'fingerprint' => $fingerprint,
            'status' => OutboundOpStatus::Sent,
            'user_id' => $actor->id,
        ]);
    }

    public function pending(string $entity, int $bitrixId): Collection
    {
        return BitrixOutboundOp::where('entity', $entity)
            ->where('bitrix_id', $bitrixId)
            ->where('status', OutboundOpStatus::Sent->value)
            ->where('created_at', '>=', now()->subSeconds(BitrixOutboundOp::WINDOW_SECONDS))
            ->latest('id')
            ->get();
    }

    public function settle(BitrixOutboundOp $op, OutboundOpStatus $status): void
    {
        BitrixOutboundOp::where('entity', $op->entity)
            ->where('bitrix_id', $op->bitrix_id)
            ->where('status', OutboundOpStatus::Sent->value)
            ->where('id', '<=', $op->id)
            ->update(['status' => $status->value, 'updated_at' => now()]);
        $op->status = $status;
    }
}
