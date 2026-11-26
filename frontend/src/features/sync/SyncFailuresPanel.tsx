import { useState } from 'react';
import { useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import bitrixSyncApi from '@/api/bitrixSyncApi';
import { queryKeys } from '@/api/queryKeys';
import Panel from '@/components/layout/Panel';
import ConfirmModal from '@/components/ui/ConfirmModal';
import ErrorState from '@/components/ui/ErrorState';
import useApiMutation from '@/hooks/useApiMutation';
import type { BitrixSyncFailure } from '@/types/api';
import SyncFailuresTable from './SyncFailuresTable';
import { failureLabel } from './syncLabels';

/** The server lists at most this many (BitrixSyncFailureService::MAX_LISTED). */
const MAX_LISTED = 100;

interface SyncFailuresPanelProps {
  failures: UseQueryResult<BitrixSyncFailure[]>;
  /** Every open failure; the list stops at the server's limit (100). */
  total: number | undefined;
}

/** Step 5.2: failed background work, kept until it succeeds or someone dismisses it. */
export default function SyncFailuresPanel({ failures, total }: SyncFailuresPanelProps) {
  const queryClient = useQueryClient();
  const [toDismiss, setToDismiss] = useState<BitrixSyncFailure | null>(null);

  // A refusal (e.g. 409: closed or already queued meanwhile) means the rows on screen are stale: reload them.
  const refresh = (): void => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.bitrixSync.all });
  };

  const retry = useApiMutation({
    mutationFn: (failure: BitrixSyncFailure) => bitrixSyncApi.retryFailure(failure.id),
    successMessage: 'Retry queued. The row closes once it succeeds.',
    errorMessage: 'Could not queue the retry.',
    invalidate: [queryKeys.bitrixSync.all],
    onError: () => refresh(),
  });

  const dismiss = useApiMutation({
    mutationFn: (failure: BitrixSyncFailure) => bitrixSyncApi.dismissFailure(failure.id),
    successMessage: 'Failure dismissed.',
    errorMessage: 'Could not dismiss the failure.',
    invalidate: [queryKeys.bitrixSync.all],
    onSuccess: () => setToDismiss(null),
    onError: () => {
      setToDismiss(null);
      refresh();
    },
  });

  const shown = failures.data?.length ?? 0;
  // Only once the list has loaded, and only past the server's limit (not for a counter a poll behind).
  const cutOff = failures.data !== undefined && total !== undefined && shown >= MAX_LISTED && total > shown;

  const busyId = retry.isPending ? (retry.variables?.id ?? null) : dismiss.isPending ? (dismiss.variables?.id ?? null) : null;

  return (
    <Panel
      title="Failed operations"
      description={
        cutOff
          ? `Showing the ${shown} most recent of ${total}. Retry or dismiss them to see the rest.`
          : 'Background work that failed after all its tries. Retry once Bitrix24 is reachable again.'
      }
      flush
    >
      {failures.isError ? (
        <ErrorState message={failures.error} onRetry={() => failures.refetch()} />
      ) : (
        <SyncFailuresTable
          failures={failures.data ?? []}
          isLoading={failures.isPending}
          busyId={busyId}
          onRetry={(failure) => retry.mutate(failure)}
          onDismiss={setToDismiss}
        />
      )}

      <ConfirmModal
        isOpen={toDismiss !== null}
        onClose={() => setToDismiss(null)}
        onConfirm={() => {
          if (toDismiss) dismiss.mutate(toDismiss);
        }}
        title="Dismiss this failure?"
        message={`${toDismiss ? failureLabel(toDismiss) : ''} will not be retried. The next sync of it, or the nightly import, may still bring it up to date.`}
        confirmText="Dismiss"
        danger
        loading={dismiss.isPending}
      />
    </Panel>
  );
}
