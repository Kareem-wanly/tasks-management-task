import { keepPreviousData, useQuery } from '@tanstack/react-query';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import useDebounce from '@/hooks/useDebounce';
import useProjectOptions from '@/hooks/queries/useProjectOptions';
import { useAuth } from '@/lib/auth/AuthContext';
import type { ProjectOption, Task } from '@/types/api';

interface CommandSearch {
  projects: ProjectOption[];
  tasks: Task[];
  searchingTasks: boolean;
}

const MIN_TASK_QUERY = 2;

/** Data for the command palette: every project (cached) and a server-side task search. */
export default function useCommandSearch(search: string, open: boolean): CommandSearch {
  const { can } = useAuth();
  const term = useDebounce(search.trim(), 250);
  const params = { search: term, per_page: 8 };

  const projects = useProjectOptions({ includeArchived: true, enabled: open && can('projects.view') });
  const tasks = useQuery({
    queryKey: queryKeys.tasks.list(params),
    queryFn: ({ signal }) => tasksApi.getAll(params, { signal }),
    enabled: open && can('tasks.view') && term.length >= MIN_TASK_QUERY,
    placeholderData: keepPreviousData,
  });

  const hasTerm = term.length >= MIN_TASK_QUERY;
  return {
    projects: projects.data ?? [],
    tasks: hasTerm ? (tasks.data?.items ?? []) : [],
    searchingTasks: hasTerm && tasks.isFetching,
  };
}
