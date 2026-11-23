<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Contracts\BitrixImportServiceInterface;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;

/**
 * Step 2.4: the full import from Bitrix24, in dependency order (tasks need
 * their users and projects). Every part is idempotent, so it can be re-run.
 * The command only reads its options and shows progress; BitrixImportService
 * runs the import.
 */
final class ImportFromBitrixCommand extends Command
{
    protected $signature = 'bitrix:import
        {--only= : Comma-separated subset of users,projects,tasks,checklists,time,files,results,chat}
        {--sync : Run now in this process, with a progress bar, instead of queueing}
        {--link-by-email : Link Bitrix24 users to existing local accounts with the same email (never accounts with administrative rights)}
        {--status : Show the progress of the last import and exit}';

    protected $description = 'Import users, projects, tasks and their checklists, time logs, files, results and chats from Bitrix24';

    public function handle(BitrixImportServiceInterface $import): int
    {
        if ($this->option('status')) {
            return $this->showStatus($import);
        }

        $entities = $this->entities();
        if ($entities === null) {
            return self::FAILURE;
        }

        return $this->option('sync') ? $this->runNow($import, $entities) : $this->queue($import, $entities);
    }

    /**
     * @return list<string>|null In run order; null when --only names something unknown.
     */
    private function entities(): ?array
    {
        $all = BitrixImportServiceInterface::ORDER;
        $only = $this->option('only');
        if ($only === null || $only === '') {
            return $all;
        }

        $wanted = array_map('trim', explode(',', $only));
        $unknown = array_diff($wanted, $all);
        if ($unknown !== []) {
            $this->error('Unknown --only value: '.implode(', ', $unknown).'. Use: '.implode(',', $all).'.');

            return null;
        }

        return array_values(array_intersect($all, $wanted));
    }

    /**
     * @param  list<string>  $entities
     */
    private function queue(BitrixImportServiceInterface $import, array $entities): int
    {
        $import->queueImport($entities, (bool) $this->option('link-by-email'));

        $this->info('Queued on "bitrix": '.implode(' → ', $entities).'.');
        $this->line('Run a worker: php artisan queue:work --queue=bitrix');
        $this->line('Watch progress: php artisan bitrix:import --status');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $entities
     */
    private function runNow(BitrixImportServiceInterface $import, array $entities): int
    {
        foreach ($entities as $entity) {
            $this->line("Importing {$entity}...");
            $bar = $this->output->createProgressBar();
            $bar->start();

            try {
                $import->runImport($entity, (bool) $this->option('link-by-email'), fn (int $processed, int $total) => $this->advance($bar, $processed, $total));
            } catch (Throwable $e) {
                $bar->clear();
                $this->newLine();
                $this->error("Importing {$entity} failed: {$e->getMessage()}");

                return self::FAILURE;
            }

            $bar->finish();
            $this->newLine();
        }

        $this->newLine();

        return $this->showStatus($import);
    }

    /** The import reports after every page; mirror it on the bar. */
    private function advance(ProgressBar $bar, int $processed, int $total): void
    {
        if ($total > 0) {
            $bar->setMaxSteps($total);
            $bar->setProgress(min($processed, $total));
        }
    }

    private function showStatus(BitrixImportServiceInterface $import): int
    {
        $entities = BitrixImportServiceInterface::ORDER;
        $cursors = $import->progress($entities);

        $this->table(['Entity', 'Status', 'Processed', 'Total', 'Last synced', 'Error'], array_map(function (string $entity) use ($cursors): array {
            $cursor = $cursors->get($entity);

            return [
                $entity,
                $cursor->status->value ?? 'never run',
                $cursor->processed ?? 0,
                $cursor->total ?? 0,
                $cursor?->last_synced_at?->toDateTimeString() ?? '-',
                $cursor->error ?? '',
            ];
        }, $entities));

        return self::SUCCESS;
    }
}
