<?php

declare(strict_types=1);

namespace App\Enums;

/** How an install call ended, which decides the answer BitrixInstallController gives. */
enum BitrixInstallOutcome
{
    /** The ONAPPINSTALL event was accepted: JSON for Bitrix's server. */
    case EventInstalled;
    /** The install page was accepted: the page that calls BX24.installFinish(). */
    case PageInstalled;
    /** The install page was refused: the admin sees why, as a page. */
    case PageRefused;
    /** Neither the event nor the page. */
    case Unsupported;
}
