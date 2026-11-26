<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Bitrix\BitrixSyncEntityDTO;
use App\DTOs\Bitrix\BitrixSyncStatusDTO;
use App\DTOs\Bitrix\StartBitrixSyncDTO;
use App\Enums\BitrixSyncMode;
use App\Enums\BitrixSyncOperation;
use App\Enums\OutboundOpStatus;
use App\Enums\SyncCursorStatus;
use App\Enums\SyncEntityState;
use App\Exceptions\Bitrix\BitrixSyncAlreadyRunningException;
use App\Jobs\Bitrix\SyncBitrixProjectJob;
use App\Jobs\Bitrix\SyncBitrixTaskChatJob;
use App\Jobs\Bitrix\SyncBitrixTaskJob;
use App\Models\BitrixOutboundOp;
use App\Models\BitrixSyncCursor;
use App\Models\BitrixSyncFailure;
use App\Repositories\Contracts\BitrixOutboundOpRepositoryInterface;
use App\Services\Contracts\BitrixImportServiceInterface;
use App\Services\Contracts\BitrixReconciliationServiceInterface;
use App\Services\Contracts\BitrixSyncFailureServiceInterface;
use App\Services\Contracts\BitrixSyncStatusServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

final readonly class BitrixSyncStatusService implements BitrixSyncStatusServiceInterface
{
    /** What the page lists: the full import's entities in order, then the 5-minute changes sync. */
    public const ENTITIES = [...BitrixImportServiceInterface::ORDER, BitrixImportServiceInterface::TASKS_CHANGES];

    /** The import jobs' time limit: a run still "running" after it, died with its worker. */
    public const STALLED_AFTER_SECONDS = 3600;

    /** How far back the conflict and unconfirmed counts look. */
    public const RECENT_HOURS = 24;

    /**
     * When the last sync was asked for. Until a cursor starts after it, the
     * request still waits in the queue and shows no running cursor, so a
     * second press would queue the same work twice.
     */
    public const REQUESTED_AT_KEY = 'bitrix-sync-requested-at';

    /** A request the worker never picked up (worker down) stops blocking after this. */
    public const REQUEST_EXPIRES_SECONDS = 600;

    public function __construct(
        private BitrixImportServiceInterface $import,
        private BitrixOutboundOpRepositoryInterface $outboundOps,
        private BitrixSyncFailureServiceInterface $failures,
        private Cache $cache,
        private BitrixReconciliationServiceInterface $reconciliation,
    ) {}

    public function status(): BitrixSyncStatusDTO
    {
        $now = CarbonImmutable::now();
        $cursors = $this->import->progress(self::ENTITIES);
        $recent = $now->subHours(self::RECENT_HOURS);

        return new BitrixSyncStatusDTO(
            entities: array_map(fn (string $entity): BitrixSyncEntityDTO => $this->entity($entity, $cursors->get($entity), $now), self::ENTITIES),
            failures: $this->failures->countOpen(),
            conflicts: $this->outboundOps->countSentBetween(OutboundOpStatus::Conflict, $recent, $now),
            // Still waiting inside the echo window is normal; only older ones count.
            unconfirmed: $this->outboundOps->countSentBetween(OutboundOpStatus::Sent, $recent, $now->subSeconds(BitrixOutboundOp::WINDOW_SECONDS)),
            reconciliation: $this->reconciliation->summary(),
        );
    }

    public function start(StartBitrixSyncDTO $request): BitrixSyncStatusDTO
    {
        $this->ensureNoImportPending();
        $this->rememberImportRequest();

        match ($request->mode) {
            BitrixSyncMode::Changes => $this->import->queueChangesSync(),
            BitrixSyncMode::Full => $this->import->queueImport(BitrixImportServiceInterface::ORDER, linkByEmail: false),
        };

        return $this->status();
    }

    public function retry(BitrixSyncFailure $failure): BitrixSyncFailure
    {
        // An import queued over a running or waiting one would race it, or be
        // skipped by its overlap lock while the rest of its chain still ran.
        $isImport = $failure->operation === BitrixSyncOperation::Import;
        if ($isImport) {
            $this->ensureNoImportPending();
        }

        $failure = $this->failures->markRetrying($failure);
        $subject = $failure->subject;

        match ($failure->operation) {
            BitrixSyncOperation::Task => dispatch(new SyncBitrixTaskJob((int) $subject)),
            BitrixSyncOperation::Project => dispatch(new SyncBitrixProjectJob((int) $subject)),
            BitrixSyncOperation::TaskChat => dispatch(new SyncBitrixTaskChatJob((int) $subject)),
            BitrixSyncOperation::Import => $this->retryImport($subject),
        };

        if ($isImport) {
            $this->rememberImportRequest();
        }

        return $failure;
    }

    /** @throws BitrixSyncAlreadyRunningException */
    private function ensureNoImportPending(): void
    {
        $status = $this->status();
        if ($status->isRunning()) {
            throw BitrixSyncAlreadyRunningException::running();
        }
        if ($this->isWaitingInQueue($status)) {
            throw BitrixSyncAlreadyRunningException::queued();
        }
    }

    private function rememberImportRequest(): void
    {
        $this->cache->put(self::REQUESTED_AT_KEY, CarbonImmutable::now()->startOfSecond()->getTimestamp(), self::REQUEST_EXPIRES_SECONDS);
    }

    /** The last request was queued, but no import has started since. */
    private function isWaitingInQueue(BitrixSyncStatusDTO $status): bool
    {
        $requestedAt = $this->cache->get(self::REQUESTED_AT_KEY);
        if (! is_int($requestedAt)) {
            return false;
        }

        foreach ($status->entities as $entity) {
            if ($entity->startedAt instanceof CarbonImmutable && $entity->startedAt->getTimestamp() >= $requestedAt) {
                return false;
            }
        }

        return true;
    }

    /**
     * A pass of the full import is retried with the passes after it, as the
     * chain it failed in never ran them; the changes sync is retried alone.
     */
    private function retryImport(string $entity): void
    {
        if ($entity === BitrixImportServiceInterface::TASKS_CHANGES) {
            $this->import->queueChangesSync();

            return;
        }

        $from = array_search($entity, BitrixImportServiceInterface::ORDER, true);
        $this->import->queueImport(
            $from === false ? [$entity] : array_slice(BitrixImportServiceInterface::ORDER, $from),
            linkByEmail: false,
        );
    }

    private function entity(string $entity, ?BitrixSyncCursor $cursor, CarbonImmutable $now): BitrixSyncEntityDTO
    {
        return new BitrixSyncEntityDTO(
            entity: $entity,
            state: $this->state($cursor, $now),
            processed: $cursor->processed ?? 0,
            total: $cursor->total ?? 0,
            error: $cursor?->error,
            startedAt: $cursor?->started_at,
            lastSyncedAt: $cursor?->last_synced_at,
        );
    }

    private function state(?BitrixSyncCursor $cursor, CarbonImmutable $now): SyncEntityState
    {
        return match ($cursor?->status) {
            null => SyncEntityState::Never,
            SyncCursorStatus::Completed => SyncEntityState::Completed,
            SyncCursorStatus::Failed => SyncEntityState::Failed,
            SyncCursorStatus::Running => $cursor->started_at instanceof CarbonImmutable && $cursor->started_at->addSeconds(self::STALLED_AFTER_SECONDS)->isAfter($now)
                ? SyncEntityState::Running
                : SyncEntityState::Stalled,
        };
    }
}
