<?php

declare(strict_types=1);

use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Models\BitrixPortal;
use App\Models\User;
use App\Repositories\Contracts\BitrixTokenRepositoryInterface;
use Carbon\CarbonImmutable;

function bitrixToken(array $scopes = []): BitrixToken
{
    return new BitrixToken('new-access', 'new-refresh', CarbonImmutable::parse('2026-11-23 12:00:00'), scopes: $scopes);
}

it('keeps a user\'s token on the user', function (): void {
    $user = User::factory()->create();

    resolve(BitrixTokenRepositoryInterface::class)->store($user, bitrixToken());

    expect($user->fresh()?->bitrixToken())
        ->accessToken->toBe('new-access')
        ->refreshToken->toBe('new-refresh');
});

it('keeps the portal\'s scopes when a refresh answer leaves them out', function (): void {
    $portal = BitrixPortal::forceCreate(['member_id' => 'member-1', 'domain' => 'portal.test', 'application_token' => 'app-token', 'scopes' => ['task', 'user']]);
    $tokens = resolve(BitrixTokenRepositoryInterface::class);

    $tokens->store($portal, bitrixToken());
    expect($portal->fresh()?->scopes)->toBe(['task', 'user']);

    $tokens->store($portal, bitrixToken(scopes: ['task']));
    expect($portal->fresh()?->bitrixToken())
        ->accessToken->toBe('new-access')
        ->scopes->toBe(['task']);
});

it('refuses an owner it cannot save', function (): void {
    $owner = new class implements HasBitrixToken
    {
        public function bitrixToken(): ?BitrixToken
        {
            return null;
        }

        public function bitrixTokenLockKey(): string
        {
            return 'test';
        }
    };

    expect(fn () => resolve(BitrixTokenRepositoryInterface::class)->store($owner, bitrixToken()))
        ->toThrow(LogicException::class, 'Only a user or the portal keeps a Bitrix24 token');
});
