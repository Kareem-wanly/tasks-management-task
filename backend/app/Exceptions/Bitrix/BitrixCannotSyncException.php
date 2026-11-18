<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

/**
 * Our data can't be expressed in Bitrix (e.g. a user who isn't in Bitrix);
 * the message says why and is shown as is.
 */
final class BitrixCannotSyncException extends BitrixException {}
