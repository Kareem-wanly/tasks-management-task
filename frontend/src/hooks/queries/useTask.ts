import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import type { Task } from '@/types/api';

/** One task with its project, assignee and comments. Shared by the task page and the top bar breadcrumb. */
export default function useTask(id: number | string | null | undefined): UseQueryResult<Task> {
  const key = id ? String(id) : '';
  return useQuery({
    queryKey: queryKeys.tasks.detail(key),
    queryFn: ({ signal }) => tasksApi.getById(key, { signal }),
    enabled: key !== '',
  });
}
