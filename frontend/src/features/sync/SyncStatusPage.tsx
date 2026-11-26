import { useState } from 'react';
import { DatabaseZapIcon, RefreshCwIcon } from 'lucide-react';
import bitrixSyncApi from '@/api/bitrixSyncApi';
import { queryKeys } from '@/api/queryKeys';
import PageHeader from '@/components/layout/PageHeader';
import ConfirmModal from '@/components/ui/ConfirmModal';
import ErrorState from '@/components/ui/ErrorState';
import { Button } from '@/components/ui/button';
import useApiMutation from '@/hooks/useApiMutation';
import type { BitrixSyncMode } from '@/types/api';
import SyncCounters from './SyncCounters';
import SyncEntitiesTable from './SyncEntitiesTable';
import SyncFailuresPanel from './SyncFailuresPanel';
import { hasRetryWaiting, useSyncFailures } from './useSyncFailures';
import { useSyncStatus } from './useSyncStatus';

/** Bitrix24 step 5.1: is the sync healthy, and a way to run it now. */
export default function SyncStatusPage() {
  const failures = useSyncFailures();
  const status = useSyncStatus(failures.data ? hasRetryWaiting(failures.data) : false);
  const [confirmFull, setConfirmFull] = useState<boolean>(false);

  const start = useApiMutation({
    mutationFn: (mode: BitrixSyncMode) => bitrixSyncApi.start(mode),
    successMessage: (_data, mode) => (mode === 'full' ? 'Full import queued.' : 'Sync queued.'),
    errorMessage: 'Could not start the sync.',
    invalidate: [queryKeys.bitrixSync.all],
    onSuccess: () => setConfirmFull(false),
  });

  const running = status.data?.is_running === true;
  const busy = start.isPending || running;

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Management"
        title="Bitrix24 sync"
        description="Changes sync every 5 minutes and everything is imported again each night. The buttons queue the same work now; the background worker must be running."
        actions={
          <>
            <Button type="button" variant="outline" disabled={busy} onClick={() => setConfirmFull(true)}>
              <DatabaseZapIcon />
              Full import
            </Button>
            <Button type="button" disabled={busy} onClick={() => start.mutate('changes')}>
              <RefreshCwIcon className={running ? 'animate-spin' : undefined} />
              {running ? 'Syncing…' : 'Sync changes now'}
            </Button>
          </>
        }
      />

      {status.isError ? (
        <ErrorState message={status.error} onRetry={() => status.refetch()} />
      ) : (
        <>
          {status.data && <SyncCounters status={status.data} />}
          <SyncEntitiesTable entities={status.data?.entities ?? []} isLoading={status.isPending} />
        </>
      )}

      <SyncFailuresPanel failures={failures} total={status.data?.failures} />

      <ConfirmModal
        isOpen={confirmFull}
        onClose={() => setConfirmFull(false)}
        onConfirm={() => start.mutate('full')}
        title="Run a full import?"
        message="Everything is read from Bitrix24 again, and records deleted there are removed here. It can take several minutes."
        confirmText="Start full import"
        loading={start.isPending}
      />
    </div>
  );
}
