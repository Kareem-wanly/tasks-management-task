<?php

namespace App\Console\Commands;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Models\TaskLookup;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use Illuminate\Console\Command;

/**
 * Pulls the task STATUS and PRIORITY values from Bitrix24 (tasks.task.getFields):
 * renames our Bitrix-linked rows and adds any value we don't have yet.
 * Our own values (is_system = false) are never touched.
 */
class SyncBitrixTaskLookups extends Command
{
    protected $signature = 'bitrix:sync-task-lookups';

    protected $description = 'Sync task statuses and priorities from Bitrix24';

    public function handle(BitrixClient $bitrix): int
    {
        try {
            $fields = $bitrix->callAsService('tasks.task.getFields')['result']['fields'] ?? [];
        } catch (BitrixException $e) {
            $this->error("Bitrix24 request failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $statuses = $fields['STATUS']['values'] ?? null;
        $priorities = $fields['PRIORITY']['values'] ?? null;

        if (! is_array($statuses) || ! is_array($priorities)) {
            $this->error('Bitrix24 did not return STATUS / PRIORITY values.');

            return self::FAILURE;
        }

        $rows = [
            ...$this->sync(TaskStatus::class, 'status', $statuses),
            ...$this->sync(TaskPriority::class, 'priority', $priorities),
        ];

        $this->table(['Type', 'Bitrix ID', 'Key', 'Name', 'Change'], $rows);
        $this->info('Task statuses and priorities are in sync with Bitrix24.');

        return self::SUCCESS;
    }

    /**
     * @param  class-string<TaskLookup>  $model
     * @param  array<int|string, string>  $values  Bitrix ID => name
     * @return list<array{string, int, string, string, string}>
     */
    private function sync(string $model, string $type, array $values): array
    {
        $rows = [];

        foreach ($values as $bitrixId => $name) {
            $bitrixId = (int) $bitrixId;
            $lookup = $model::where('is_system', true)->where('bitrix_id', $bitrixId)->first();

            if (! $lookup) {
                $lookup = new $model(['key' => "bitrix_{$type}_{$bitrixId}", 'bitrix_id' => $bitrixId, 'color' => 'neutral']);
                $lookup->is_system = true;
                $lookup->sort = $model::max('sort') + 1;
                $change = 'added';
            } else {
                $change = $lookup->name === $name ? 'unchanged' : 'renamed';
            }

            $lookup->name = $name;
            $lookup->save();

            $rows[] = [$type, $bitrixId, $lookup->key, $name, $change];
        }

        return $rows;
    }
}
