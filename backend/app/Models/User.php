<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

class User extends Authenticatable implements HasBitrixToken
{
    use HasApiTokens,HasFactory, Notifiable;

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

    protected ?Collection $permissionCache = null;

    protected ?Collection $roleCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'bitrix_access_token' => 'encrypted',
            'bitrix_refresh_token' => 'encrypted',
            'bitrix_token_expires_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'bitrix_synced_at' => 'datetime',
        ];
    }

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

    public function storeBitrixToken(BitrixToken $token): void
    {
        $this->forceFill([
            'bitrix_access_token' => $token->accessToken,
            'bitrix_refresh_token' => $token->refreshToken,
            'bitrix_token_expires_at' => $token->expiresAt,
        ])->save();
    }

    public function bitrixTokenLockKey(): string
    {
        return "bitrix-token:user:{$this->id}";
    }

    public function permissionNames(): Collection
    {
        return $this->permissionCache ??= $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasPermission(string $name): bool
    {
        return $this->permissionNames()->contains($name);
    }

    public function hasRole(string $name): bool
    {
        return ($this->roleCache ??= $this->roles()->pluck('name'))->contains($name);
    }

    public function memberProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function permissionIds(): Collection
    {
        return $this->roles()
            ->with('permissions:id')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values();
    }

    public function clearPermissionsCache(): void
    {
        $this->permissionCache = null;
        $this->roleCache = null;
    }
}
