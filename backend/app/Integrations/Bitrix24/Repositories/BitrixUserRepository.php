<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Repositories;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Integrations\Bitrix24\Contracts\BitrixUserRepositoryInterface;
use App\Models\User;

final readonly class BitrixUserRepository implements BitrixUserRepositoryInterface
{
    public function __construct(private BitrixGatewayInterface $bitrix) {}

    public function invite(User $actor, string $email, string $name, int $departmentId): int
    {
        $bitrixId = (int) ($this->bitrix->callAsUser($actor, 'user.add', [
            'EMAIL' => $email,
            ...$this->nameFields($name),
            // Bitrix requires a department for an employee; they can be moved there later.
            'UF_DEPARTMENT' => [$departmentId],
        ])['result'] ?? 0);

        return $bitrixId ?: throw BitrixException::cannotSync('Bitrix24 did not return the invited user.');
    }

    public function update(User $actor, int $bitrixUserId, ?string $name, ?string $email): void
    {
        $fields = $name !== null ? $this->nameFields($name) : [];
        if ($email !== null) {
            $fields['EMAIL'] = $email;
        }

        $this->bitrix->callAsUser($actor, 'user.update', ['ID' => $bitrixUserId, ...$fields]);
    }

    public function setActive(User $actor, int $bitrixUserId, bool $active): void
    {
        $this->bitrix->callAsUser($actor, 'user.update', ['ID' => $bitrixUserId, 'ACTIVE' => $active ? 'Y' : 'N']);
    }

    public function eachPage(callable $onPage): int
    {
        // No ACTIVE filter: ACTIVE=true would drop deactivated users, and we
        // need them to block their access here too.
        return $this->bitrix->eachPageAsService('user.get', ['sort' => 'ID', 'order' => 'ASC'], function (array $rows, int $total) use ($onPage): void {
            /** @var list<array<string, mixed>> $rows user.get answers a list of users */
            $onPage($rows, $total);
        });
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
}
