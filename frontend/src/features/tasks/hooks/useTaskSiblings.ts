import { useQuery } from '@tanstack/react-query';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';

interface TaskSiblings {
  previousId: number | null;
  nextId: number | null;
  /** 1-based position in the project, or null while loading or when not found. */
  position: number | null;
  count: number;
}

// The backend caps per_page at 100; tasks past that simply get no prev/next links
const params = (projectId: number) => ({ project_id: projectId, per_page: 100, sort_by: 'created_at', sort_order: 'desc' });

/** Previous / next task in the same project, in the order the project's task list shows them. */
export default function useTaskSiblings(projectId: number | undefined, taskId: number): TaskSiblings {
  const query = useQuery({
    queryKey: queryKeys.tasks.list(params(projectId ?? 0)),
    queryFn: ({ signal }) => tasksApi.getAll(params(projectId ?? 0), { signal }),
    enabled: projectId !== undefined,
    staleTime: 60_000,
  });

  const ids = (query.data?.items ?? []).map((t) => t.id);
  const index = ids.indexOf(taskId);
  if (index === -1) return { previousId: null, nextId: null, position: null, count: ids.length };

  return {
    previousId: ids[index - 1] ?? null,
    nextId: ids[index + 1] ?? null,
    position: index + 1,
    count: query.data?.total ?? ids.length,
  };
}
