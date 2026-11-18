<?php

namespace App\Integrations\Bitrix24;

use App\Models\BitrixPortal;
use App\Models\User;

/**
 * Step 3.3: sends employee changes made in our app to Bitrix24, with the
 * acting employee's token (Bitrix requires the right to invite or edit
 * users). Called before the local save: when Bitrix refuses, it throws and
 * nothing is saved here. Bitrix has no user delete over REST, so users are
 * deactivated instead.
 */
class BitrixUserPusher
{
    public function __construct(private readonly BitrixClient $bitrix) {}

    /**
     * Invites the (unsaved) user: Bitrix emails them an invitation, and the
     * returned ID links the accounts, so their later Bitrix sign-in finds
     * this user.
     */
    public function invite(User $actor, User $user): void
    {
        $this->ensureLinked($actor);
        $department = $this->rootDepartmentId();

        $bitrixId = (int) ($this->bitrix->callAsUser($actor, 'user.add', [
            'EMAIL' => $user->email,
            ...$this->nameFields($user->name),
            // Bitrix requires a department for an employee; they can be moved there later.
            'UF_DEPARTMENT' => [$department],
        ])['result'] ?? 0);

        $user->bitrix_user_id = $bitrixId ?: throw BitrixException::cannotSync('Bitrix24 did not return the invited user.');
        $user->bitrix_department_ids = [$department];
        $user->bitrix_synced_at = now();
    }

    /**
     * Sends the user's unsaved name/email changes.
     */
    public function update(User $actor, User $user): void
    {
        if (! $user->bitrix_user_id || ! $user->isDirty(['name', 'email'])) {
            return;
        }

        $fields = [];
        if ($user->isDirty('name')) {
            $fields = $this->nameFields($user->name);
        }
        if ($user->isDirty('email')) {
            $fields['EMAIL'] = $user->email;
        }

        $this->ensureLinked($actor);
        $this->bitrix->callAsUser($actor, 'user.update', ['ID' => $user->bitrix_user_id, ...$fields]);
        $user->bitrix_synced_at = now();
    }

    public function setActive(User $actor, User $user, bool $active): void
    {
        if (! $user->bitrix_user_id) {
            return;
        }

        $this->ensureLinked($actor);
        $this->bitrix->callAsUser($actor, 'user.update', ['ID' => $user->bitrix_user_id, 'ACTIVE' => $active ? 'Y' : 'N']);
        $user->bitrix_synced_at = now();
    }

    private function ensureLinked(User $actor): void
    {
        if (! $actor->isLinkedToBitrix()) {
            throw BitrixException::missingToken();
        }
    }

    /**
     * Our single name field as Bitrix NAME / LAST_NAME: first word, then the rest.
     *
     * @return array{NAME: string, LAST_NAME: string}
     */
    private function nameFields(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [''];

        return ['NAME' => $parts[0], 'LAST_NAME' => $parts[1] ?? ''];
    }

    /** The top of the company structure (the department without a parent). */
    private function rootDepartmentId(): int
    {
        foreach (BitrixPortal::current()?->departments ?? [] as $department) {
            if (empty($department['PARENT'])) {
                return (int) $department['ID'];
            }
        }

        throw BitrixException::cannotSync('The Bitrix24 company structure is unknown; reinstall the app to load it.');
    }
}
