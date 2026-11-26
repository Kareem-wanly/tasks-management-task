<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\User\InviteUserDTO;
use App\DTOs\User\SetUserActiveDTO;
use App\DTOs\User\UpdateUserDTO;
use App\DTOs\User\UserFiltersDTO;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface UserServiceInterface
{
    /** @return LengthAwarePaginator<int, User> */
    public function list(UserFiltersDTO $filters, int $perPage): LengthAwarePaginator;

    /**
     * Only active employees can be picked as assignees or members.
     *
     * @return Collection<int, User>
     */
    public function options(): Collection;

    public function show(User $user): User;

    /**
     * Invites the person to Bitrix24 (Bitrix emails them), then adds them
     * here with the chosen role, or Member.
     */
    public function invite(User $actor, InviteUserDTO $data): User;

    public function update(User $actor, User $user, UpdateUserDTO $data): User;

    /**
     * Replaces deleting: Bitrix24 has no user delete over REST, so employees
     * are deactivated (and can be activated again). A deactivated employee
     * can't sign in, and their open sessions end.
     */
    public function setActive(User $actor, User $user, SetUserActiveDTO $data): User;

    /** A new API (Sanctum) token for the user; returns its plain text. */
    public function issueApiToken(User $user, string $name): string;

    /** Throws ModelNotFoundException (404) when there is none. */
    public function findOrFail(int $id): User;

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, User>
     */
    public function findMany(array $userIds): Collection;
}
