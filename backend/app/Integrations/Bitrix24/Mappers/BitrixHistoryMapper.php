<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Mappers;

use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Repositories\Contracts\TaskLookupRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\CarbonImmutable;

/**
 * Step 4.8: turns tasks.task.history.list rows into entries the task page
 * can show: Bitrix status/priority numbers become our keys, Bitrix user IDs
 * become names, timestamps become ISO dates. Other values pass through.
 */
class BitrixHistoryMapper
{
    /** Fields whose values are Bitrix user IDs (one, or several separated by commas). */
    private const PEOPLE_FIELDS = ['RESPONSIBLE_ID', 'CREATED_BY', 'CHANGED_BY', 'ACCOMPLICES', 'AUDITORS'];

    /** Fields whose values are Unix timestamps. */
    private const DATE_FIELDS = ['DEADLINE', 'START_DATE_PLAN', 'END_DATE_PLAN'];

    public function __construct(
        private readonly TaskLookupRepositoryInterface $lookups,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{id: int, field: string, from: ?string, to: ?string, user: array{id: ?int, name: string}|null, created_at: ?string}>
     */
    public function entries(array $rows): array
    {
        [$names, $ourIds] = $this->people($rows);

        $entries = array_map(fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'field' => (string) ($row['field'] ?? ''),
            'from' => $this->value((string) ($row['field'] ?? ''), $row['value']['from'] ?? null, $names),
            'to' => $this->value((string) ($row['field'] ?? ''), $row['value']['to'] ?? null, $names),
            'user' => $this->author($row['user'] ?? null, $names, $ourIds),
            'created_at' => $this->date($row['createdDate'] ?? null),
        ], $rows);

        // Newest first, as the task page reads it.
        usort($entries, fn (array $a, array $b): int => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);

        return $entries;
    }

    /** @param  array<int, string>  $names  Bitrix user ID => name. */
    private function value(string $field, mixed $value, array $names): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (true) {
            $field === 'STATUS' => $this->lookups->keyForBitrix(TaskStatus::class, (int) $value) ?? (string) $value,
            $field === 'PRIORITY' => $this->lookups->keyForBitrix(TaskPriority::class, (int) $value) ?? (string) $value,
            in_array($field, self::PEOPLE_FIELDS, true) => implode(', ', array_map(
                fn (int $id) => $names[$id] ?? "Bitrix user {$id}",
                $this->ids($value),
            )),
            in_array($field, self::DATE_FIELDS, true) && is_numeric($value) => CarbonImmutable::createFromTimestamp((int) $value)->toIso8601String(),
            // Bitrix names lists made without a title BX_CHECKLIST_1, 2… and shows them as "Checklist 1".
            str_starts_with($field, 'CHECKLIST_') => (string) preg_replace('/^BX_CHECKLIST_(\d+)$/', 'Checklist $1', (string) $value),
            default => (string) $value,
        };
    }

    /**
     * @param  array<int, string>  $names  Bitrix user ID => name.
     * @param  array<int, int>  $ourIds  Bitrix user ID => our user ID.
     * @return array{id: ?int, name: string}|null
     */
    private function author(mixed $user, array $names, array $ourIds): ?array
    {
        if (! is_array($user) || empty($user['id'])) {
            return null;
        }

        $bitrixId = (int) $user['id'];
        $ourId = $ourIds[$bitrixId] ?? null;
        $bitrixName = trim(($user['name'] ?? '').' '.($user['lastName'] ?? ''));

        return ['id' => $ourId, 'name' => $names[$bitrixId] ?? ($bitrixName !== '' ? $bitrixName : "Bitrix user {$bitrixId}")];
    }

    /**
     * Our IDs and names for every Bitrix user the rows mention, in two queries.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: array<int, string>, 1: array<int, int>} [Bitrix ID => name, Bitrix ID => our ID]
     */
    private function people(array $rows): array
    {
        $bitrixIds = [];
        foreach ($rows as $row) {
            $bitrixIds[] = (int) ($row['user']['id'] ?? 0);
            if (in_array($row['field'] ?? '', self::PEOPLE_FIELDS, true)) {
                array_push($bitrixIds, ...$this->ids($row['value']['from'] ?? ''), ...$this->ids($row['value']['to'] ?? ''));
            }
        }

        $ourIds = $this->users->idsByBitrixIds(array_values(array_unique(array_filter($bitrixIds))));
        $users = $this->users->findMany(array_values($ourIds))->keyBy('id');

        $names = [];
        foreach ($ourIds as $bitrixId => $ourId) {
            if ($user = $users->get($ourId)) {
                /** @var User $user */
                $names[(int) $bitrixId] = $user->name;
            }
        }

        return [$names, $ourIds];
    }

    /** @return list<int> */
    private function ids(mixed $value): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $value))));
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->toIso8601String() : null;
    }
}
