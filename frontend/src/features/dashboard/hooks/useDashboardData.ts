import { useQuery } from '@tanstack/react-query';
import projectsApi from '@/api/projectsApi';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import type { Project, Task } from '@/types/api';

const LIST = { per_page: 100 };
// One-row pages: only `total` is read, so the counts cover every task, not just a loaded page
const OVERDUE = { overdue: 'true', per_page: 1 };
// Every closing status (is_final), not only "completed"
const COMPLETED = { finished: 'true', per_page: 1 };

export interface DashboardData {
  projects: Project[];
  /** The latest 100 tasks, for the breakdowns and the chart. */
  tasks: Task[];
  tasksTotal: number;
  /** Tasks assigned to the user, soonest due first (up to 100). */
  myTasks: Task[];
  overdueTotal: number;
  completedTotal: number;
  isPending: boolean;
  isError: boolean;
  error: Error | null;
  refetch: () => void;
}

export default function useDashboardData(userId: number | undefined): DashboardData {
  const mine = { assigned_to: userId ?? 0, per_page: 100, sort_by: 'due_date', sort_order: 'asc' };

  const queries = [
    useQuery({
      queryKey: queryKeys.projects.list(LIST),
      queryFn: ({ signal }) => projectsApi.getAll(LIST, { signal }),
    }),
    useQuery({ queryKey: queryKeys.tasks.list(LIST), queryFn: ({ signal }) => tasksApi.getAll(LIST, { signal }) }),
    useQuery({
      queryKey: queryKeys.tasks.list(mine),
      queryFn: ({ signal }) => tasksApi.getAll(mine, { signal }),
      enabled: userId !== undefined,
    }),
    useQuery({ queryKey: queryKeys.tasks.list(OVERDUE), queryFn: ({ signal }) => tasksApi.getAll(OVERDUE, { signal }) }),
    useQuery({ queryKey: queryKeys.tasks.list(COMPLETED), queryFn: ({ signal }) => tasksApi.getAll(COMPLETED, { signal }) }),
  ] as const;
  const [projectsQuery, tasksQuery, mineQuery, overdueQuery, completedQuery] = queries;

  return {
    projects: projectsQuery.data?.items ?? [],
    tasks: tasksQuery.data?.items ?? [],
    tasksTotal: tasksQuery.data?.total ?? 0,
    myTasks: mineQuery.data?.items ?? [],
    overdueTotal: overdueQuery.data?.total ?? 0,
    completedTotal: completedQuery.data?.total ?? 0,
    isPending: projectsQuery.isPending || tasksQuery.isPending,
    isError: queries.some((q) => q.isError),
    error: queries.find((q) => q.error)?.error ?? null,
    refetch: () => queries.forEach((q) => q.isError && void q.refetch()),
  };
}
