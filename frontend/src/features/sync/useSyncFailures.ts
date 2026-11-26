import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import bitrixSyncApi from '@/api/bitrixSyncApi';
import { queryKeys } from '@/api/queryKeys';
import type { BitrixSyncFailure } from '@/types/api';

/** Faster while a fresh retry is queued, so the row disappears soon after it succeeds. */
const RETRYING_POLL_MS = 5_000;
const IDLE_POLL_MS = 30_000;

export function useSyncFailures(): UseQueryResult<BitrixSyncFailure[]> {
  return useQuery({
    queryKey: queryKeys.bitrixSync.failures,
    queryFn: ({ signal }) => bitrixSyncApi.getFailures({ signal }),
    refetchInterval: (query) =>
      query.state.data && hasRetryWaiting(query.state.data) ? RETRYING_POLL_MS : IDLE_POLL_MS,
  });
}

/** A retry was queued moments ago and the worker has not finished it yet. */
export function hasRetryWaiting(failures: BitrixSyncFailure[]): boolean {
  return failures.some((failure) => failure.status === 'retrying' && !failure.can_retry);
}
