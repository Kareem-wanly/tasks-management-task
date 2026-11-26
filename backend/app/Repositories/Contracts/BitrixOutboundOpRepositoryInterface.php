<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\OutboundOpAction;
use App\Enums\OutboundOpEntity;
use App\Enums\OutboundOpStatus;
use App\Models\BitrixOutboundOp;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Step 3.5: the changes we send to Bitrix24, so their echoes are recognised.
 */
interface BitrixOutboundOpRepositoryInterface
{
    public function record(OutboundOpEntity $entity, int $bitrixId, OutboundOpAction $action, ?string $fingerprint, User $actor): void;

    /**
     * Our recent unanswered changes to the record, newest first.
     *
     * @return Collection<int, BitrixOutboundOp>
     */
    public function pending(OutboundOpEntity $entity, int $bitrixId): Collection;

    /** Answers this change and every earlier one to the same record still waiting. */
    public function settle(BitrixOutboundOp $op, OutboundOpStatus $status): void;

    /** Step 5.1: how many of our changes sent in [$from, $to] stand at $status. */
    public function countSentBetween(OutboundOpStatus $status, CarbonImmutable $from, CarbonImmutable $to): int;
}
