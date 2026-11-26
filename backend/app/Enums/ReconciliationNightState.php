<?php

declare(strict_types=1);

namespace App\Enums;

/** Step 5.3: one day of the nightly reconciliation of one entity. */
enum ReconciliationNightState: string
{
    /** Checked, and every check that day matched. */
    case Clean = 'clean';

    /** At least one check that day found a difference. */
    case Differences = 'differences';

    /** No full import compared anything that day: it breaks a clean streak. */
    case NoCheck = 'none';
}
