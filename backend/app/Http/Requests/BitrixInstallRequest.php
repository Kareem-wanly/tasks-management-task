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

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** The install, from the event's auth block or from the page's parameters; neither: unsupported. */
    public function toDto(): BitrixInstallDTO
    {
        if ($this->input('event') === 'ONAPPINSTALL') {
            return BitrixInstallDTO::fromEvent((array) $this->input('auth', []), $this->input('data.LANGUAGE_ID'));
        }

        return $this->filled('AUTH_ID') ? BitrixInstallDTO::fromPage($this->all()) : BitrixInstallDTO::unsupported();
    }
}
