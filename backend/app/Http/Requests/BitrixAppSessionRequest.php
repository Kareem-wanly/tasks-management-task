<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\Bitrix\BitrixFrameAuthDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The app opened inside Bitrix24: the frontend sends the tokens the Bitrix
 * JS SDK gave it (BX24.getAuth(), from the Bitrix page around it). They are
 * only trusted after Bitrix confirms them.
 */
class BitrixAppSessionRequest extends FormRequest
{
    /** Bitrix tokens live an hour; used when the SDK's expiry can't be read. */
    private const DEFAULT_LIFETIME_SECONDS = 3600;

    public function authorize(): bool
    {
        // Public: the Bitrix tokens are the proof, checked with Bitrix.
        return true;
    }

    public function rules(): array
    {
        return [
            'access_token' => ['required', 'string', 'max:255'],
            'refresh_token' => ['required', 'string', 'max:255'],
            // The SDK may send seconds left or the expiry time itself (see lifetimeSeconds()).
            'expires_in' => ['nullable', 'integer', 'min:1'],
            'domain' => ['required', 'string', 'max:255'],
            'member_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** The validated tokens, with expires_in as seconds left. */
    public function toDto(): BitrixFrameAuthDTO
    {
        return new BitrixFrameAuthDTO(
            accessToken: (string) $this->validated('access_token'),
            refreshToken: (string) $this->validated('refresh_token'),
            expiresIn: $this->lifetimeSeconds(),
            domain: (string) $this->validated('domain'),
            memberId: $this->validated('member_id'),
        );
    }

    /**
     * Seconds the access token has left. BX24.getAuth() gives either that,
     * or the expiry as a Unix time (seconds or milliseconds).
     */
    private function lifetimeSeconds(): int
    {
        $value = (int) ($this->validated('expires_in') ?? 0);

        if ($value > 100_000_000_000) {
            $value = intdiv($value, 1000) - time();
        } elseif ($value > 100_000_000) {
            $value -= time();
        }

        return $value > 0 && $value <= 86400 ? $value : self::DEFAULT_LIFETIME_SECONDS;
    }
}
