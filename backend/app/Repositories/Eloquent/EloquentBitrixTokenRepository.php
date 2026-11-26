<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Models\BitrixPortal;
use App\Models\User;
use App\Repositories\Contracts\BitrixTokenRepositoryInterface;
use LogicException;

final class EloquentBitrixTokenRepository implements BitrixTokenRepositoryInterface
{
    public function store(HasBitrixToken $holder, BitrixToken $token): void
    {
        match (true) {
            $holder instanceof User => $holder->forceFill([
                'bitrix_access_token' => $token->accessToken,
                'bitrix_refresh_token' => $token->refreshToken,
                'bitrix_token_expires_at' => $token->expiresAt,
            ])->save(),
            $holder instanceof BitrixPortal => $holder->forceFill([
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'token_expires_at' => $token->expiresAt,
                // A refresh answer may leave the scopes out: keep the ones from the install.
                'scopes' => $token->scopes !== [] ? $token->scopes : $holder->scopes,
            ])->save(),
            default => throw new LogicException('Only a user or the portal keeps a Bitrix24 token, not '.$holder::class.'.'),
        };
    }
}
