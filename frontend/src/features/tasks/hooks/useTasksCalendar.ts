import { useState } from 'react';
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import type { QueryParams } from '@/lib/paginate';
import type { Page, Task } from '@/types/api';
import { daysFromNow, monthGridDays, startOfMonth, toDateOnly } from '@/utils/dates';
import useTaskQuickUpdate from './useTaskQuickUpdate';

/** The backend caps a page at 100; a busier month shows a notice with the real total. */
const CALENDAR_LIMIT = 100;

export interface TasksCalendarData {
  month: string;
  setMonth: (month: string) => void;
  /** The 42 days of the visible grid. */
  days: string[];
  tasksByDay: Map<string, Task[]>;
  /** Tasks due in the visible range on the server, which can exceed those loaded. */
  total: number;
  loaded: number;
  isPending: boolean;
  isError: boolean;
  /** Reschedules a task to `date` (drag and drop), with Undo. */
  move: (task: Task, date: string) => void;
}

interface UseTasksCalendarOptions {
  /** GET /tasks filters (project, assignee, priority…); the due-date range is set by the calendar. */
  params: QueryParams;
  enabled: boolean;
}

/** Tasks by due date for a month grid, using the same filters as the list and the board. */
export default function useTasksCalendar({ params, enabled }: UseTasksCalendarOptions): TasksCalendarData {
  const queryClient = useQueryClient();
  const quick = useTaskQuickUpdate();
  const { isFinal } = useTaskLookups();
  const [month, setMonth] = useState(() => startOfMonth(daysFromNow(0)));
  const days = monthGridDays(month);

  const query: QueryParams = {
    ...params,
    page: undefined,
    due_date_from: days[0],
    due_date_to: days[days.length - 1],
    per_page: CALENDAR_LIMIT,
    sort_by: 'due_date',
    sort_order: 'asc',
  };
  const key = queryKeys.tasks.list(query);

  const result = useQuery({
    queryKey: key,
    queryFn: ({ signal }) => tasksApi.getAll(query, { signal }),
    placeholderData: keepPreviousData,
    enabled,
  });

  const tasksByDay = new Map<string, Task[]>();
  for (const task of result.data?.items ?? []) {
    const day = toDateOnly(task.due_date);
    if (!day) continue;
    tasksByDay.set(day, [...(tasksByDay.get(day) ?? []), task]);
  }

  const move = (task: Task, date: string) => {
    if (toDateOnly(task.due_date) === date) return;
    void queryClient.cancelQueries({ queryKey: key });
    const overdue = !isFinal(task.status) && date < daysFromNow(0);
    queryClient.setQueryData<Page<Task>>(key, (page) =>
      page
        ? { ...page, items: page.items.map((t) => (t.id === task.id ? { ...t, due_date: date, is_overdue: overdue } : t)) }
        : page
    );
    quick.extend(task, date);
  };

  return {
    month,
    setMonth,
    days,
    tasksByDay,
    total: result.data?.total ?? 0,
    loaded: result.data?.items.length ?? 0,
    isPending: result.isPending,
    isError: result.isError,
    move,
  };
}
