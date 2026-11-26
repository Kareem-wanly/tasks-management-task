<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Mappers;

use App\Integrations\Bitrix24\BitrixPayload;
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
final readonly class BitrixHistoryMapper
{
    /** Fields whose values are Bitrix user IDs (one, or several separated by commas). */
    private const PEOPLE_FIELDS = ['RESPONSIBLE_ID', 'CREATED_BY', 'CHANGED_BY', 'ACCOMPLICES', 'AUDITORS'];

    /** Fields whose values are Unix timestamps. */
    private const DATE_FIELDS = ['DEADLINE', 'START_DATE_PLAN', 'END_DATE_PLAN'];

    public function __construct(
        private TaskLookupRepositoryInterface $lookups,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{id: int, field: string, from: ?string, to: ?string, user: array{id: ?int, name: string}|null, created_at: ?string}>
     */
    public function entries(array $rows): array
    {
        $rows = array_map(fn (array $row): BitrixPayload => BitrixPayload::of($row, 'tasks.task.history.list'), $rows);
        [$names, $ourIds] = $this->people($rows);

        $entries = array_map(function (BitrixPayload $row) use ($names, $ourIds): array {
            $field = $row->stringOrNull('field') ?? '';

            return [
                'id' => $row->intOrNull('id') ?? 0,
                'field' => $field,
                'from' => $this->value($field, $row->stringOrNull('value.from'), $names),
                'to' => $this->value($field, $row->stringOrNull('value.to'), $names),
                'user' => $this->author($row->payload('user'), $names, $ourIds),
                'created_at' => $row->dateOrNull('createdDate')?->toIso8601String(),
            ];
        }, $rows);

        // Newest first, as the task page reads it.
        usort($entries, fn (array $a, array $b): int => [$b['created_at'], $b['id']] <=> [$a['created_at'], $a['id']]);

        return $entries;
    }

    /** @param  array<int, string>  $names  Bitrix user ID => name. */
    private function value(string $field, ?string $value, array $names): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (true) {
            $field === 'STATUS' => $this->lookups->keyForBitrix(TaskStatus::class, (int) $value) ?? $value,
            $field === 'PRIORITY' => $this->lookups->keyForBitrix(TaskPriority::class, (int) $value) ?? $value,
            in_array($field, self::PEOPLE_FIELDS, true) => implode(', ', array_map(
                fn (int $id): string => $names[$id] ?? "Bitrix user {$id}",
                $this->ids($value),
            )),
            in_array($field, self::DATE_FIELDS, true) && is_numeric($value) => CarbonImmutable::createFromTimestamp((int) $value)->toIso8601String(),
            // Bitrix names lists made without a title BX_CHECKLIST_1, 2… and shows them as "Checklist 1".
            str_starts_with($field, 'CHECKLIST_') => preg_replace('/^BX_CHECKLIST_(\d+)$/', 'Checklist $1', $value) ?? $value,
            default => $value,
        };
    }

    /**
     * @param  array<int, string>  $names  Bitrix user ID => name.
     * @param  array<int, int>  $ourIds  Bitrix user ID => our user ID.
     * @return array{id: ?int, name: string}|null
     */
    private function author(BitrixPayload $user, array $names, array $ourIds): ?array
    {
        $bitrixId = $user->intOrNull('id');
        if ($bitrixId === null || $bitrixId === 0) {
            return null;
        }

        $bitrixName = trim(($user->stringOrNull('name') ?? '').' '.($user->stringOrNull('lastName') ?? ''));

        return ['id' => $ourIds[$bitrixId] ?? null, 'name' => $names[$bitrixId] ?? ($bitrixName !== '' ? $bitrixName : "Bitrix user {$bitrixId}")];
    }

    /**
     * Our IDs and names for every Bitrix user the rows mention, in two queries.
     *
     * @param  list<BitrixPayload>  $rows
     * @return array{0: array<int, string>, 1: array<int, int>} [Bitrix ID => name, Bitrix ID => our ID]
     */
    private function people(array $rows): array
    {
        $bitrixIds = [];
        foreach ($rows as $row) {
            $bitrixIds[] = $row->intOrNull('user.id') ?? 0;
            if (in_array($row->stringOrNull('field'), self::PEOPLE_FIELDS, true)) {
                array_push($bitrixIds, ...$this->ids($row->stringOrNull('value.from')), ...$this->ids($row->stringOrNull('value.to')));
            }
        }

        $ourIds = $this->users->idsByBitrixIds(array_values(array_unique(array_filter($bitrixIds))));
        $users = $this->users->findMany(array_values($ourIds))->keyBy('id');

        $names = [];
        foreach ($ourIds as $bitrixId => $ourId) {
            $user = $users->get($ourId);
            if ($user instanceof User) {
                $names[$bitrixId] = $user->name;
            }
        }

        return [$names, $ourIds];
    }

    /** @return list<int> */
    private function ids(?string $value): array
    {
        return array_values(array_filter(array_map(fn (string $id): int => (int) $id, explode(',', $value ?? ''))));
    }
}
