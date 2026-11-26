<?php

declare(strict_types=1);

use App\Actions\Bitrix\SyncBitrixUserAction;
use App\Enums\SystemRole;
use App\Integrations\Bitrix24\Mappers\BitrixUserMapper;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

beforeEach(function (): void {
    $this->users = Mockery::mock(UserRepositoryInterface::class);
    $this->roles = Mockery::mock(RoleRepositoryInterface::class);
    $this->action = new SyncBitrixUserAction(new BitrixUserMapper, $this->users, $this->roles);
    $this->row = ['ID' => '42', 'NAME' => 'Rama', 'LAST_NAME' => 'D', 'EMAIL' => 'rama@example.com', 'ACTIVE' => true];
    $this->users->shouldReceive('findByBitrixId')->with(42)->andReturnNull();
});

/** A local account with the same email, its permissions known without the database. */
function localAccount(string ...$roles): User
{
    $user = userWithRoles(...$roles)->forceFill(['email' => 'rama@example.com']);
    (fn (): Illuminate\Support\Collection => $this->permissionCache = collect())->call($user);

    return $user;
}

it('never links an administrator account by email, even on an import that links', function (): void {
    $this->users->shouldReceive('findUnlinkedByEmail')->andReturn(localAccount(SystemRole::Administrator->value));
    $this->users->shouldNotReceive('save');
    $this->users->shouldNotReceive('forceCreate');

    expect($this->action->handle($this->row, linkByEmail: true))->toBeNull();
});

it('does not link by email unless the caller asks (sign-in)', function (): void {
    $this->users->shouldReceive('findUnlinkedByEmail')->andReturn(localAccount('Member'));
    $this->users->shouldNotReceive('save');

    expect($this->action->handle($this->row))->toBeNull();
});

it('links a member account by email on an import that links', function (): void {
    $local = localAccount('Member');
    $this->users->shouldReceive('findUnlinkedByEmail')->andReturn($local);
    $this->users->shouldReceive('save')->once()->with($local);

    expect($this->action->handle($this->row, linkByEmail: true))->toBe($local)
        ->and($local->bitrix_user_id)->toBe(42);
});

it('creates a new Member when nobody matches, with a placeholder email when Bitrix has none', function (): void {
    config(['bitrix.portal' => 'portal.test']);
    $created = new User;
    $this->users->shouldReceive('forceCreate')->once()
        ->with(Mockery::on(fn (array $attributes): bool => $attributes['email'] === 'bitrix-42@portal.test'
            && $attributes['name'] === 'Rama D' && $attributes['is_active'] === true && $attributes['password'] === null))
        ->andReturn($created);
    $this->roles->shouldReceive('findByName')->with(SystemRole::Member->value)->andReturn((new Role)->forceFill(['id' => 3]));
    $this->users->shouldReceive('attachRole')->once()->with($created, 3);

    expect($this->action->handle([...$this->row, 'EMAIL' => '']))->toBe($created);
});
