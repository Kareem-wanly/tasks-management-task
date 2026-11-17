<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A change we sent to Bitrix24 (step 3.5). Bitrix echoes it back as an
 * event: matching the fingerprint confirms it; a different record within
 * the window means someone changed it in Bitrix right after us (conflict).
 * Stored and queried through BitrixOutboundOpRepository.
 */
class BitrixOutboundOp extends Model
{
    public const SENT = 'sent';

    public const CONFIRMED = 'confirmed';

    public const CONFLICT = 'conflict';

    public const TASK = 'task';

    public const PROJECT = 'project';

    /** How long after sending an incoming event is taken as the echo of our change. */
    public const WINDOW_SECONDS = 60;

    protected $fillable = ['entity', 'bitrix_id', 'action', 'fingerprint', 'status', 'user_id'];

    public function isDelete(): bool
    {
        return $this->action === 'delete';
    }
}
