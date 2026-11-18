<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\QueueName;
use App\Jobs\Bitrix\ImportBitrixChat;
use App\Jobs\Bitrix\ImportBitrixChecklists;
use App\Jobs\Bitrix\ImportBitrixFiles;
use App\Jobs\Bitrix\ImportBitrixProjects;
use App\Jobs\Bitrix\ImportBitrixResults;
use App\Jobs\Bitrix\ImportBitrixTasks;
use App\Jobs\Bitrix\ImportBitrixTime;
use App\Jobs\Bitrix\ImportBitrixUsers;
use App\Models\BitrixSyncCursor;
use App\Repositories\Contracts\BitrixSyncCursorRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

/**
 * Step 2.4: the full import from Bitrix24, in dependency order (tasks need
 * their users and projects). Every part is idempotent, so it can be re-run.
 */
class ImportFromBitrix extends Command
{
    /** In the order they must run. */
    private const JOBS = [
        ImportBitrixUsers::ENTITY => ImportBitrixUsers::class,
        ImportBitrixProjects::ENTITY => ImportBitrixProjects::class,
        ImportBitrixTasks::ENTITY => ImportBitrixTasks::class,
        ImportBitrixChecklists::ENTITY => ImportBitrixChecklists::class,
        ImportBitrixTime::ENTITY => ImportBitrixTime::class,
        ImportBitrixFiles::ENTITY => ImportBitrixFiles::class,
        ImportBitrixResults::ENTITY => ImportBitrixResults::class,
        ImportBitrixChat::ENTITY => ImportBitrixChat::class,
    ];

    protected $signature = 'bitrix:import
        {--only= : Comma-separated subset of users,projects,tasks,checklists,time,files,results,chat}
        {--sync : Run now in this process, with a progress bar, instead of queueing}
        {--link-by-email : Link Bitrix24 users to existing local accounts with the same email (never accounts with administrative rights)}
        {--status : Show the progress of the last import and exit}';

    protected $description = 'Import users, projects, tasks and their checklists, time logs, files, results and chats from Bitrix24';

    private ?ProgressBar $bar = null;

    private ?string $running = null;

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        $entities = $this->entities();
        if ($entities === null) {
            return self::FAILURE;
        }

        return $this->option('sync') ? $this->runNow($entities) : $this->queue($entities);
    }

    /**
     * @return list<string>|null In run order; null when --only names something unknown.
     */
    private function entities(): ?array
    {
        $only = $this->option('only');
        if ($only === null || $only === '') {
            return array_keys(self::JOBS);
        }

        $wanted = array_map('trim', explode(',', $only));
        $unknown = array_diff($wanted, array_keys(self::JOBS));
        if ($unknown !== []) {
            $this->error('Unknown --only value: '.implode(', ', $unknown).'. Use: '.implode(',', array_keys(self::JOBS)).'.');

            return null;
        }

        return array_values(array_intersect(array_keys(self::JOBS), $wanted));
    }

    private function job(string $entity): object
    {
        return $entity === ImportBitrixUsers::ENTITY
            ? new ImportBitrixUsers((bool) $this->option('link-by-email'))
            : new (self::JOBS[$entity]);
    }

    /**
     * @param  list<string>  $entities
     */
    private function queue(array $entities): int
    {
        // A chain stops at the first failure, so tasks never run on missing users or projects.
        Bus::chain(array_map($this->job(...), $entities))
            ->onQueue(QueueName::Bitrix)
            ->dispatch();

        $this->info('Queued on "bitrix": '.implode(' → ', $entities).'.');
        $this->line('Run a worker: php artisan queue:work --queue=bitrix');
        $this->line('Watch progress: php artisan bitrix:import --status');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $entities
     */
    private function runNow(array $entities): int
    {
        // The jobs save their cursor after every page; mirror it on the bar.
        BitrixSyncCursor::saved(function (BitrixSyncCursor $cursor): void {
            if ($this->bar instanceof ProgressBar && $cursor->entity === $this->running && $cursor->total > 0) {
                $this->bar->setMaxSteps($cursor->total);
                $this->bar->setProgress(min($cursor->processed, $cursor->total));
            }
        });

        foreach ($entities as $entity) {
            $this->line("Importing {$entity}...");
            $this->running = $entity;
            $this->bar = $this->output->createProgressBar();
            $this->bar->start();

            try {
                dispatch_sync($this->job($entity));
            } catch (Throwable $e) {
                $this->bar->clear();
                $this->newLine();
                $this->error("Importing {$entity} failed: {$e->getMessage()}");

                return self::FAILURE;
            }

            $this->bar->finish();
            $this->newLine();
        }

        $this->bar = null;
        $this->newLine();

        return $this->showStatus();
    }

    private function showStatus(): int
    {
        $cursors = resolve(BitrixSyncCursorRepositoryInterface::class)->forEntities(array_keys(self::JOBS));

        $this->table(['Entity', 'Status', 'Processed', 'Total', 'Last synced', 'Error'], array_map(function (string $entity) use ($cursors): array {
            $cursor = $cursors->get($entity);

            return [
                $entity,
                $cursor?->status ?? 'never run',
                $cursor?->processed ?? 0,
                $cursor?->total ?? 0,
                $cursor?->last_synced_at?->toDateTimeString() ?? '-',
                $cursor?->error ?? '',
            ];
        }, array_keys(self::JOBS)));

        return self::SUCCESS;
    }
}
