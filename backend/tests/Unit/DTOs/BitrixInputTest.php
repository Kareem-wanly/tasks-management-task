<?php

declare(strict_types=1);

use App\DTOs\Bitrix\BitrixEventDTO;
use App\DTOs\Bitrix\BitrixInstallDTO;

it('reads an install from the ONAPPINSTALL event', function (): void {
    $install = BitrixInstallDTO::fromEvent([
        'member_id' => 'm1', 'domain' => 'portal.test', 'application_token' => 'app',
        'access_token' => 'acc', 'refresh_token' => 'ref', 'expires_in' => '3600', 'scope' => 'task,user',
    ], 'en');

    expect([$install->memberId, $install->domain, $install->applicationToken, $install->accessToken, $install->refreshToken])
        ->toBe(['m1', 'portal.test', 'app', 'acc', 'ref'])
        ->and($install->expiresIn)->toBe(3600)
        ->and($install->scope)->toBe('task,user')
        ->and($install->language)->toBe('en');
});

it('reads an install from the install page', function (): void {
    $install = BitrixInstallDTO::fromPage([
        'member_id' => 'm1', 'DOMAIN' => 'portal.test', 'APPLICATION_TOKEN' => 'app',
        'AUTH_ID' => 'acc', 'REFRESH_ID' => 'ref', 'AUTH_EXPIRES' => '3600', 'APPLICATION_SCOPE' => 'task', 'LANG' => 'ar',
    ]);

    expect([$install->memberId, $install->domain, $install->applicationToken, $install->accessToken, $install->refreshToken])
        ->toBe(['m1', 'portal.test', 'app', 'acc', 'ref'])
        ->and($install->language)->toBe('ar');
});

it('leaves missing install values null for the service to refuse', function (): void {
    $install = BitrixInstallDTO::fromPage(['DOMAIN' => 'portal.test', 'AUTH_ID' => '']);

    expect($install->accessToken)->toBeNull()
        ->and($install->memberId)->toBeNull()
        ->and($install->expiresIn)->toBeNull()
        ->and($install->scope)->toBe('');
});

it('upper-cases the event name and keeps its data', function (): void {
    $event = BitrixEventDTO::fromArray(['event' => 'onTaskUpdate', 'data' => ['FIELDS_AFTER' => ['ID' => '15']]]);

    expect($event->event)->toBe('ONTASKUPDATE')
        ->and($event->data)->toBe(['FIELDS_AFTER' => ['ID' => '15']])
        ->and(BitrixEventDTO::fromArray([])->event)->toBe('');
});
