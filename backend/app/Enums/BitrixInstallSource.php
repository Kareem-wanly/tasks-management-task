<?php

declare(strict_types=1);

namespace App\Enums;

/** The two ways Bitrix24 reports an app install. */
enum BitrixInstallSource
{
    /** The ONAPPINSTALL event, server to server. */
    case Event;
    /** The install page, opened by the admin's browser inside Bitrix24. */
    case Page;
}
