import { keepPreviousData, useQueries, useQueryClient } from '@tanstack/react-query';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import type { QueryParams } from '@/lib/paginate';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import type { Page, Task, TaskPriority, TaskStatus, User } from '@/types/api';
import useTaskQuickUpdate from './useTaskQuickUpdate';
import useTaskStatus from './useTaskStatus';

/** Cards loaded per column; the column links to the full list when it holds more. */
export const BOARD_COLUMN_LIMIT = 50;

export interface BoardColumnData {
  status: TaskStatus;
  tasks: Task[];
  /** Server-side count for the column, which can exceed the cards shown. */
  total: number;
  isPending: boolean;
  isError: boolean;
}

/** Dropping a card into another swimlane also changes the field the lanes group by. */
export type LaneChange = { priority: TaskPriority } | { assignee: User | null };

export interface TasksBoardData {
  columns: BoardColumnData[];
  move: (task: Task, to: TaskStatus, laneChange?: LaneChange) => void;
}

interface UseTasksBoardOptions {
  /** GET /tasks filters and sort (project_id, search, assigned_to, sort_by...). Any `status` is replaced per column. */
  params: QueryParams;
  enabled: boolean;
}

const columnParams = (params: QueryParams, status: TaskStatus): QueryParams => ({
  ...params,
  status,
  page: undefined,
  per_page: BOARD_COLUMN_LIMIT,
});

/**
 * Kanban data: one GET /tasks query per status column, so every column has an exact count.
 * A move updates both columns at once, then useTaskStatus saves it (Undo in the toast,
 * and a failed save reloads the lists so the card snaps back).
 */
export default function useTasksBoard({ params, enabled }: UseTasksBoardOptions): TasksBoardData {
  const queryClient = useQueryClient();
  const status = useTaskStatus();
  const quick = useTaskQuickUpdate();
  const { statuses, priorityLabel, isFinal } = useTaskLookups();
  const columnStatuses = statuses.map((s) => s.key);

  const results = useQueries({
    queries: columnStatuses.map((s) => {
      const query = columnParams(params, s);
      return {
        queryKey: queryKeys.tasks.list(query),
        queryFn: ({ signal }: { signal: AbortSignal }) => tasksApi.getAll(query, { signal }),
        placeholderData: keepPreviousData,
        enabled,
      };
    }),
  });

  const columns: BoardColumnData[] = columnStatuses.map((s, i) => ({
    status: s,
    tasks: results[i]?.data?.items ?? [],
    total: results[i]?.data?.total ?? 0,
    isPending: results[i]?.isPending ?? true,
    isError: results[i]?.isError ?? false,
  }));

  const move = (task: Task, to: TaskStatus, laneChange?: LaneChange) => {
    const statusChanged = task.status !== to;
    if (!statusChanged && !laneChange) return;
    const fromKey = queryKeys.tasks.list(columnParams(params, task.status));
    const toKey = queryKeys.tasks.list(columnParams(params, to));

    // A refetch already in flight would overwrite the optimistic move
    void queryClient.cancelQueries({ queryKey: fromKey });
    void queryClient.cancelQueries({ queryKey: toKey });

    const moved: Task = {
      ...task,
      status: to,
      is_overdue: isFinal(to) ? false : task.is_overdue,
      ...(laneChange && 'priority' in laneChange ? { priority: laneChange.priority } : {}),
      ...(laneChange && 'assignee' in laneChange ? { assignee: laneChange.assignee } : {}),
    };
    if (statusChanged) {
      queryClient.setQueryData<Page<Task>>(fromKey, (page) =>
        page ? { ...page, items: page.items.filter((t) => t.id !== task.id), total: Math.max(page.total - 1, 0) } : page
      );
      queryClient.setQueryData<Page<Task>>(toKey, (page) =>
        page ? { ...page, items: [moved, ...page.items], total: page.total + 1 } : page
      );
      status.change(task, to);
    } else {
      queryClient.setQueryData<Page<Task>>(fromKey, (page) =>
        page ? { ...page, items: page.items.map((t) => (t.id === task.id ? moved : t)) } : page
      );
    }

    if (laneChange && 'priority' in laneChange) {
      quick.update(task, { priority: laneChange.priority }, `Priority set to ${priorityLabel(laneChange.priority)}.`);
    } else if (laneChange) {
      quick.assignTo(task, laneChange.assignee?.id ?? null, laneChange.assignee?.name);
    }
  };

  return { columns, move };
}
