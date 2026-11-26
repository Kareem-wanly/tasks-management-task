import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import bitrixSyncApi from '@/api/bitrixSyncApi';
import { queryKeys } from '@/api/queryKeys';
import type { BitrixSyncStatus } from '@/types/api';

/** Fast while a sync runs or a retry waits, so progress and the counters move; slow otherwise. */
const RUNNING_POLL_MS = 3_000;
const IDLE_POLL_MS = 30_000;

export function useSyncStatus(retryWaiting: boolean): UseQueryResult<BitrixSyncStatus> {
  return useQuery({
    queryKey: queryKeys.bitrixSync.status,
    queryFn: ({ signal }) => bitrixSyncApi.getStatus({ signal }),
    refetchInterval: (query) => (query.state.data?.is_running || retryWaiting ? RUNNING_POLL_MS : IDLE_POLL_MS),
  });
}
