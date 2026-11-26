import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import bitrixSyncApi from '@/api/bitrixSyncApi';
import { queryKeys } from '@/api/queryKeys';
import type { BitrixSyncStatus } from '@/types/api';

/** Fast while a sync runs, so its progress moves; slow otherwise. */
const RUNNING_POLL_MS = 3_000;
const IDLE_POLL_MS = 30_000;

export function useSyncStatus(): UseQueryResult<BitrixSyncStatus> {
  return useQuery({
    queryKey: queryKeys.bitrixSync.status,
    queryFn: ({ signal }) => bitrixSyncApi.getStatus({ signal }),
    refetchInterval: (query) => (query.state.data?.is_running ? RUNNING_POLL_MS : IDLE_POLL_MS),
  });
}
