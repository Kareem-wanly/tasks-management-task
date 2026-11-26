<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Bitrix\BitrixInstallDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bitrix24's install call (event or install page). Who may install is
 * decided by checking the portal and the token with Bitrix, in the service.
 */
final class BitrixInstallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function isInstallEvent(): bool
    {
        return $this->input('event') === 'ONAPPINSTALL';
    }

    public function isInstallPage(): bool
    {
        return $this->filled('AUTH_ID');
    }

    /** The install, from the event's auth block or from the page's parameters. */
    public function toDto(): BitrixInstallDTO
    {
        return $this->isInstallEvent()
            ? BitrixInstallDTO::fromEvent((array) $this->input('auth', []), $this->input('data.LANGUAGE_ID'))
            : BitrixInstallDTO::fromPage($this->all());
    }
}
