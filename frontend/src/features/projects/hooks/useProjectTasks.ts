import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import {
  useTasksBoard,
  useTasksCalendar,
  type TasksBoardData,
  type TasksCalendarData,
} from '@/features/tasks';
import { isTasksView, type TasksView } from '@/components/data/ViewToggle';
import useDebounce from '@/hooks/useDebounce';
import { readPreference, writePreference } from '@/lib/preferences/storage';
import type { Page, Task } from '@/types/api';

export interface ProjectTasksList {
  view: TasksView;
  setView: (view: TasksView) => void;
  query: UseQueryResult<Page<Task>>;
  board: TasksBoardData;
  calendar: TasksCalendarData;
  search: string;
  setSearch: (value: string) => void;
  status: string;
  setStatus: (value: string) => void;
  page: number;
  setPage: (page: number) => void;
}

/**
 * The project's tasks through GET /tasks?project_id=, which (unlike /projects/{id}/tasks)
 * supports search and status filters and returns full tasks with `is_overdue`.
 * Filters live in the URL next to `?tab=`, so Back from a task restores them.
 */
export default function useProjectTasks(projectId: number | string, enabled: boolean): ProjectTasksList {
  const [searchParams, setSearchParams] = useSearchParams();
  const [search, setSearchState] = useState(searchParams.get('search') ?? '');
  const [status, setStatusState] = useState(searchParams.get('status') ?? '');
  const [page, setPage] = useState(Number(searchParams.get('page')) || 1);
  const [view, setViewState] = useState<TasksView>(() => {
    const saved = readPreference('project-tasks-view');
    return isTasksView(saved) ? saved : 'list';
  });
  const debouncedSearch = useDebounce(search.trim(), 400);

  useEffect(() => {
    setSearchParams(
      (prev) => {
        const next = new URLSearchParams(prev);
        const values: [string, string][] = [
          ['search', debouncedSearch],
          ['status', status],
          ['page', page > 1 ? String(page) : ''],
        ];
        for (const [key, value] of values) {
          if (value) next.set(key, value);
          else next.delete(key);
        }
        return next;
      },
      { replace: true }
    );
  }, [debouncedSearch, status, page, setSearchParams]);

  const params = { project_id: projectId, page, per_page: 10, search: debouncedSearch, status };
  const query = useQuery({
    queryKey: queryKeys.tasks.list(params),
    queryFn: ({ signal }) => tasksApi.getAll(params, { signal }),
    placeholderData: keepPreviousData,
    enabled: enabled && view === 'list',
  });
  // The board splits by status itself, so it only takes the search
  const board = useTasksBoard({
    params: { project_id: projectId, search: debouncedSearch, sort_by: 'created_at', sort_order: 'desc' },
    enabled: enabled && view === 'board',
  });
  const calendar = useTasksCalendar({
    params: { project_id: projectId, search: debouncedSearch, status },
    enabled: enabled && view === 'calendar',
  });

  return {
    view,
    setView: (next) => {
      setViewState(next);
      writePreference('project-tasks-view', next);
    },
    query,
    board,
    calendar,
    search,
    setSearch: (value) => {
      setSearchState(value);
      setPage(1);
    },
    status,
    setStatus: (value) => {
      setStatusState(value);
      setPage(1);
    },
    page,
    setPage,
  };
}
