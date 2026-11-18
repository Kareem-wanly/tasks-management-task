import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import historyApi from '@/api/historyApi';
import { queryKeys } from '@/api/queryKeys';
import type { TaskHistory } from '@/types/api';

/** A task's history (step 4.8), fetched only once the panel is opened: each read asks Bitrix24. */
export default function useTaskHistory(taskId: number, enabled: boolean): UseQueryResult<TaskHistory> {
  return useQuery({
    queryKey: queryKeys.tasks.history(taskId),
    queryFn: ({ signal }) => historyApi.get(taskId, { signal }),
    enabled,
    staleTime: 60_000,
  });
}
