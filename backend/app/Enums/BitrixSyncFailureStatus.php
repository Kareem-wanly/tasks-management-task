<?php

declare(strict_types=1);

namespace App\Enums;

/** bitrix_sync_failures.status (step 5.2). */
enum BitrixSyncFailureStatus: string
{
    /** Failed after all its tries; waits for someone to retry or dismiss it. */
    case Pending = 'pending';

    /** Queued again by hand; closed when the operation next succeeds. */
    case Retrying = 'retrying';

    /** The operation succeeded since (a retry, the next event, or a scheduled sync). */
    case Resolved = 'resolved';

    /** Closed by hand without a retry. */
    case Dismissed = 'dismissed';

    /** Still shown on the status page. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Retrying;
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Pending->value, self::Retrying->value];
    }
}
