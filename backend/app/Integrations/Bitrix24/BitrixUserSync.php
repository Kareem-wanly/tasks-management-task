<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Enums\SystemRole;
use App\Integrations\Bitrix24\Mappers\BitrixUserMapper;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates or updates our copy of a Bitrix24 user. Used by the import and by
 * Bitrix login, so both end with the same row. Bitrix wins on conflict.
 */
class BitrixUserSync
{
    public function __construct(
        private readonly BitrixUserMapper $mapper,
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * Find by Bitrix ID, else create a Member. An existing local account with
     * the same email is linked only when $linkByEmail is set (an import an
     * administrator runs on purpose) and never when it has administrative
     * rights: anyone can put any email on their Bitrix profile.
     *
     * @return User|null Null when a local account with that email exists but may not be linked.
     */
    public function upsert(array $row, bool $linkByEmail = false): ?User
    {
        $attributes = $this->mapper->attributes($row);
        $bitrixId = $attributes['bitrix_user_id'];
        $name = $this->mapper->name($row);
        $email = $this->mapper->email($row);

        return DB::transaction(function () use ($attributes, $bitrixId, $name, $email, $linkByEmail): ?\App\Models\User {
            $user = $this->users->findByBitrixId($bitrixId);

            if (! $user && $email !== null) {
                $unlinked = $this->users->findUnlinkedByEmail($email);

                if ($unlinked instanceof User && (! $linkByEmail || $unlinked->hasAdministrativeRights())) {
                    Log::warning('Bitrix24 user not linked: a local account with the same email exists.', [
                        'bitrix_user_id' => $bitrixId,
                        'local_user_id' => $unlinked->id,
                    ]);

                    return null;
                }

                $user = $unlinked;
            }

            if ($user instanceof User) {
                $this->update($user, $attributes, $name, $email);

                return $user;
            }

            $user = $this->users->forceCreate([
                // Set explicitly: the DB default isn't on the model until a reload.
                'is_active' => true,
                ...$attributes,
                'name' => $name ?? "Bitrix user {$bitrixId}",
                // users.email is required and unique; a Bitrix account may have none.
                'email' => $email !== null && ! $this->users->emailTaken($email) ? $email : $this->placeholderEmail($bitrixId),
                'password' => null,
            ]);

            if (($member = $this->roles->findByName(SystemRole::Member->value)) instanceof \App\Models\Role) {
                $this->users->attachRole($user, $member->id);
            }

            return $user;
        });
    }

    private function update(User $user, array $attributes, ?string $name, ?string $email): void
    {
        $user->forceFill($attributes);

        if ($name !== null) {
            $user->name = $name;
        }

        if ($email !== null && $email !== $user->email && ! $this->users->emailTaken($email, $user->id)) {
            $user->email = $email;
        }

        $this->users->save($user);

        // Deactivated in Bitrix: end every open session, not just future logins.
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $this->users->revokeTokens($user);
        }
    }

    private function placeholderEmail(int $bitrixId): string
    {
        return "bitrix-{$bitrixId}@".config('bitrix.portal');
    }
}
