<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A change we sent to Bitrix24 (step 3.5). Bitrix echoes it back as an
 * event: matching the fingerprint confirms it; a different record within
 * the window means someone changed it in Bitrix right after us (conflict).
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

    public static function record(string $entity, int $bitrixId, string $action, ?string $fingerprint, User $actor): self
    {
        return static::create([
            'entity' => $entity,
            'bitrix_id' => $bitrixId,
            'action' => $action,
            'fingerprint' => $fingerprint,
            'status' => self::SENT,
            'user_id' => $actor->id,
        ]);
    }

    /** Our latest unanswered change to this record, if it is recent. */
    public static function pending(string $entity, int $bitrixId): ?self
    {
        return static::where('entity', $entity)
            ->where('bitrix_id', $bitrixId)
            ->where('status', self::SENT)
            ->where('created_at', '>=', now()->subSeconds(self::WINDOW_SECONDS))
            ->latest('id')
            ->first();
    }

    public function isDelete(): bool
    {
        return $this->action === 'delete';
    }

    public function confirm(): void
    {
        $this->update(['status' => self::CONFIRMED]);
    }

    public function markConflict(): void
    {
        $this->update(['status' => self::CONFLICT]);
    }
}
