<?php

namespace App\Integrations\Bitrix24;

use App\Integrations\Bitrix24\Mappers\BitrixUserMapper;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates or updates our copy of a Bitrix24 user. Used by the import and by
 * Bitrix login, so both end with the same row. Bitrix wins on conflict.
 */
class BitrixUserSync
{
    public function __construct(private readonly BitrixUserMapper $mapper) {}

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

        return DB::transaction(function () use ($attributes, $bitrixId, $name, $email, $linkByEmail) {
            $user = User::where('bitrix_user_id', $bitrixId)->first();

            if (! $user && $email !== null) {
                $unlinked = User::whereRaw('LOWER(email) = ?', [$email])->whereNull('bitrix_user_id')->first();

                if ($unlinked && (! $linkByEmail || $unlinked->hasAdministrativeRights())) {
                    Log::warning('Bitrix24 user not linked: a local account with the same email exists.', [
                        'bitrix_user_id' => $bitrixId,
                        'local_user_id' => $unlinked->id,
                    ]);

                    return null;
                }

                $user = $unlinked;
            }

            if ($user) {
                $this->update($user, $attributes, $name, $email);

                return $user;
            }

            $user = User::forceCreate([
                // Set explicitly: the DB default isn't on the model until a reload.
                'is_active' => true,
                ...$attributes,
                'name' => $name ?? "Bitrix user {$bitrixId}",
                // users.email is required and unique; a Bitrix account may have none.
                'email' => $email !== null && ! $this->emailTaken($email) ? $email : $this->placeholderEmail($bitrixId),
                'password' => null,
            ]);

            if ($member = Role::where('name', 'Member')->first()) {
                $user->roles()->attach($member->id);
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

        if ($email !== null && $email !== $user->email && ! $this->emailTaken($email, $user->id)) {
            $user->email = $email;
        }

        $user->save();

        // Deactivated in Bitrix: end every open session, not just future logins.
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $user->tokens()->delete();
        }
    }

    private function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return User::whereRaw('LOWER(email) = ?', [$email])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    private function placeholderEmail(int $bitrixId): string
    {
        return "bitrix-{$bitrixId}@".config('bitrix.portal');
    }
}
