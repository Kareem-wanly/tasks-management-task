import { useQuery } from '@tanstack/react-query';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';

/** Server-side count of the project's overdue tasks (one-row page, read `total`). */
export default function useOverdueTaskCount(projectId: number | string, enabled: boolean): number | undefined {
  const params = { project_id: projectId, overdue: 'true', per_page: 1 };
  const query = useQuery({
    queryKey: queryKeys.tasks.list(params),
    queryFn: ({ signal }) => tasksApi.getAll(params, { signal }),
    enabled,
  });
  return query.data?.total;
}
