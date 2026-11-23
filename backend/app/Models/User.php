<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\SystemRole;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int|null $bitrix_user_id
 * @property CarbonImmutable|null $bitrix_token_expires_at
 * @property CarbonImmutable|null $bitrix_synced_at
 * @property list<int>|null $bitrix_department_ids
 */
class User extends Authenticatable implements HasBitrixToken
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Permissions that let an account manage people and their access. */
    public const ADMINISTRATIVE_PERMISSIONS = [
        'users.create', 'users.update', 'users.delete', 'users.manage_roles',
        'roles.create', 'roles.update', 'roles.delete', 'roles.assign', 'roles.manage',
        'permissions.assign',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'bitrix_access_token',
        'bitrix_refresh_token',
    ];

    /** @var Collection<int, string>|null */
    protected ?Collection $permissionCache = null;

    /** @var Collection<int, string>|null */
    protected ?Collection $roleCache = null;

    public function bitrixToken(): ?BitrixToken
    {
        if (! $this->bitrix_access_token || ! $this->bitrix_refresh_token) {
            return null;
        }

        return new BitrixToken(
            accessToken: $this->bitrix_access_token,
            refreshToken: $this->bitrix_refresh_token,
            expiresAt: $this->bitrix_token_expires_at ?? CarbonImmutable::now(),
        );
    }

    /** Signed in with Bitrix24 at least once, so we can act in Bitrix as them. */
    public function isLinkedToBitrix(): bool
    {
        return $this->bitrix_user_id !== null && $this->bitrixToken() instanceof BitrixToken;
    }

    public function bitrixTokenLockKey(): string
    {
        return "bitrix-token:user:{$this->id}";
    }

    /** @return Collection<int, string> */
    public function permissionNames(): Collection
    {
        return $this->permissionCache ??= $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (Role $role): Collection => $role->permissions->map(fn (Permission $permission): string => $permission->name))
            ->unique()
            ->values();
    }

    /**
     * @return BelongsToMany<Role, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasPermission(string $name): bool
    {
        return $this->permissionNames()->contains($name);
    }

    /** Administrators, and anyone who can manage users, roles or permissions. */
    public function hasAdministrativeRights(): bool
    {
        return $this->hasRole(SystemRole::Administrator)
            || $this->permissionNames()->intersect(self::ADMINISTRATIVE_PERMISSIONS)->isNotEmpty();
    }

    public function hasRole(SystemRole|string $name): bool
    {
        return ($this->roleCache ??= $this->roles()->get(['roles.name'])->map(fn (Role $role): string => $role->name))->contains($name instanceof SystemRole ? $name->value : $name);
    }

    /**
     * @return BelongsToMany<Project, $this, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function memberProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    /** @return Collection<int, int<0, max>> */
    public function permissionIds(): Collection
    {
        return $this->roles()
            ->with('permissions:id')
            ->get()
            ->toBase()
            ->flatMap(fn (Role $role): Collection => $role->permissions->map(fn (Permission $permission): int => $permission->id))
            ->unique()
            ->values();
    }

    public function clearPermissionsCache(): void
    {
        $this->permissionCache = null;
        $this->roleCache = null;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'bitrix_access_token' => 'encrypted',
            'bitrix_refresh_token' => 'encrypted',
            'bitrix_token_expires_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'bitrix_department_ids' => 'array', // Bitrix UF_DEPARTMENT; names live in bitrix_portals.departments.
            'bitrix_synced_at' => 'datetime',
        ];
    }
}
