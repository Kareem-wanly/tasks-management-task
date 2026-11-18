<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Bitrix\BitrixInstallRefusedException;
use App\Http\Requests\BitrixInstallRequest;
use App\Services\BitrixInstallService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "Initial installation path" of the Bitrix24 app.
 *
 * - ONAPPINSTALL event: Bitrix's server posts event/auth[...].
 * - Install page: the admin's browser opens the path. Bitrix treats the app
 *   as not installed until the page calls BX24.installFinish(), so we answer
 *   with a page that does.
 */
class BitrixInstallController extends Controller
{
    public function __construct(private readonly BitrixInstallService $installs) {}

    public function install(BitrixInstallRequest $request): Response
    {
        if ($request->isInstallEvent()) {
            $this->installs->installFromEvent((array) $request->input('auth', []), $request->input('data.LANGUAGE_ID'));

            return response()->json(['message' => 'Installed.']);
        }

        if ($request->isInstallPage()) {
            // The admin sees this page inside Bitrix, so a refusal is a page too.
            try {
                $this->installs->installFromPage($request->all());
            } catch (BitrixInstallRefusedException $e) {
                return response()->view('bitrix.install-refused', ['message' => $e->getMessage()], $e->status());
            }

            return response()->view('bitrix.install-finish');
        }

        return response()->json(['message' => 'Unsupported request.'], 400);
    }
}
