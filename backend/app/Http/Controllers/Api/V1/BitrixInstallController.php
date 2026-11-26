<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BitrixInstallOutcome;
use App\Http\Controllers\Controller;
use App\Http\Requests\BitrixInstallRequest;
use App\Services\Contracts\BitrixInstallServiceInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "Initial installation path" of the Bitrix24 app.
 *
 * - ONAPPINSTALL event: Bitrix's server posts event/auth[...].
 * - Install page: the admin's browser opens the path. Bitrix treats the app
 *   as not installed until the page calls BX24.installFinish(), so we answer
 *   with a page that does.
 *
 * The service installs and says how it ended; this only turns that into the
 * answer each caller expects.
 */
final class BitrixInstallController extends Controller
{
    public function __construct(private readonly BitrixInstallServiceInterface $installs) {}

    public function install(BitrixInstallRequest $request): Response
    {
        $result = $this->installs->install($request->toDto());

        return match ($result->outcome) {
            BitrixInstallOutcome::EventInstalled => response()->json(['message' => 'Installed.']),
            BitrixInstallOutcome::PageInstalled => response()->view('bitrix.install-finish'),
            // The admin sees this page inside Bitrix, so a refusal is a page too.
            BitrixInstallOutcome::PageRefused => response()->view('bitrix.install-refused', ['message' => $result->message], $result->status ?? Response::HTTP_FORBIDDEN),
            BitrixInstallOutcome::Unsupported => response()->json(['message' => 'Unsupported request.'], Response::HTTP_BAD_REQUEST),
        };
    }
}
