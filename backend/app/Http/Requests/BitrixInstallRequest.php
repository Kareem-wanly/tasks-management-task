<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bitrix24's install call (event or install page). Who may install is
 * decided by checking the portal and the token with Bitrix, in the service.
 */
class BitrixInstallRequest extends FormRequest
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
}
