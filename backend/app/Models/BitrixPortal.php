<?php

namespace App\Models;

use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The installed Bitrix24 portal. Its token belongs to the user who installed
 * the app and serves as the service account for background sync.
 */
class BitrixPortal extends Model implements HasBitrixToken
{
    protected $fillable = [
        'member_id',
        'domain',
        'application_token',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'app_info',
        'installer',
        'installer_is_admin',
        'departments',
        'language',
        'installed_at',
    ];

    protected $hidden = [
        'application_token',
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'application_token' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'immutable_datetime',
        'scopes' => 'array',
        'app_info' => 'array',
        'installer' => 'array',
        'installer_is_admin' => 'boolean',
        'departments' => 'array',
        'installed_at' => 'datetime',
    ];

    public static function current(): ?self
    {
        return static::where('domain', config('bitrix.portal'))->first();
    }

    public function bitrixToken(): ?BitrixToken
    {
        if (! $this->access_token || ! $this->refresh_token) {
            return null;
        }

        return new BitrixToken(
            accessToken: $this->access_token,
            refreshToken: $this->refresh_token,
            expiresAt: $this->token_expires_at ?? CarbonImmutable::now(),
            domain: $this->domain,
            memberId: $this->member_id,
            scopes: $this->scopes ?? [],
        );
    }

    public function storeBitrixToken(BitrixToken $token): void
    {
        $this->forceFill([
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'token_expires_at' => $token->expiresAt,
            'scopes' => $token->scopes !== [] ? $token->scopes : $this->scopes,
        ])->save();
    }

    public function bitrixTokenLockKey(): string
    {
        return "bitrix-token:portal:{$this->member_id}";
    }
}
