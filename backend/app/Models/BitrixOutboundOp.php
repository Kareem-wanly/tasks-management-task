<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OutboundOpAction;
use App\Enums\OutboundOpEntity;
use App\Enums\OutboundOpStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * A change we sent to Bitrix24 (step 3.5). Bitrix echoes it back as an
 * event: matching the fingerprint confirms it; a different record within
 * the window means someone changed it in Bitrix right after us (conflict).
 * Stored and queried through BitrixOutboundOpRepository.
 *
 * @property OutboundOpEntity $entity
 * @property OutboundOpAction $action
 */
class BitrixOutboundOp extends Model
{
    /** How long after sending an incoming event is taken as the echo of our change. */
    public const WINDOW_SECONDS = 60;

    protected $fillable = ['entity', 'bitrix_id', 'action', 'fingerprint', 'status', 'user_id'];

    protected $casts = [
        'entity' => OutboundOpEntity::class,
        'action' => OutboundOpAction::class,
        'status' => OutboundOpStatus::class,
    ];

    public function isDelete(): bool
    {
        return $this->action === OutboundOpAction::Delete;
    }
}
