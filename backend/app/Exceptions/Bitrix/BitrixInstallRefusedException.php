<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use App\Exceptions\BusinessException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app install was refused. From the install page the admin sees it as a
 * page (BitrixInstallController); from the install event, as JSON.
 */
final class BitrixInstallRefusedException extends BusinessException
{
    private function __construct(string $message, private readonly int $statusCode)
    {
        parent::__construct($message);
    }

    public static function missingEventAuth(): self
    {
        return new self('Missing member_id, application_token or user tokens.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function missingParameters(): self
    {
        return new self('Missing install parameters.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function applicationTokenMismatch(): self
    {
        return new self('The application token does not match this portal.', Response::HTTP_FORBIDDEN);
    }

    public static function tokenNotAccepted(): self
    {
        return new self('Bitrix24 did not accept the install token.', Response::HTTP_FORBIDDEN);
    }

    public static function tokenForAnotherApp(): self
    {
        return new self('The install token was not issued for this app.', Response::HTTP_FORBIDDEN);
    }

    public static function notAnAdministrator(): self
    {
        return new self('Only a Bitrix24 administrator can install this app. Ask your portal administrator to install it.', Response::HTTP_FORBIDDEN);
    }

    public static function unknownPortal(): self
    {
        return new self('Unknown portal.', Response::HTTP_FORBIDDEN);
    }

    public function status(): int
    {
        return $this->statusCode;
    }
}
