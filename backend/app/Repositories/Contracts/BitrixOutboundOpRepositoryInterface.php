<?php

namespace App\Repositories\Contracts;

use App\Models\BitrixOutboundOp;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Step 3.5: the changes we send to Bitrix24, so their echoes are recognised.
 */
interface BitrixOutboundOpRepositoryInterface
{
    public function record(string $entity, int $bitrixId, string $action, ?string $fingerprint, User $actor): void;

    /**
     * Our recent unanswered changes to the record, newest first.
     *
     * @return Collection<int, BitrixOutboundOp>
     */
    public function pending(string $entity, int $bitrixId): Collection;

    /** Answers this change and every earlier one to the same record still waiting. */
    public function settle(BitrixOutboundOp $op, string $status): void;
}
