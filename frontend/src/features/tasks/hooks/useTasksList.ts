import { useEffect, useReducer, useState, type Dispatch } from 'react';
import { useSearchParams } from 'react-router-dom';
import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import { isTasksView, type TasksView } from '@/components/data/ViewToggle';
import type { QueryParams } from '@/lib/paginate';
import useDebounce from '@/hooks/useDebounce';
import { readPreference, writePreference } from '@/lib/preferences/storage';
import type { Page, Task } from '@/types/api';
import {
  DEFAULT_SORTING,
  filterParams,
  stateFromSearchParams,
  tasksReducer,
  type TasksAction,
  type TasksState,
} from '../tasksReducer';
import useTasksBoard, { type TasksBoardData } from './useTasksBoard';
import useTasksCalendar, { type TasksCalendarData } from './useTasksCalendar';

interface UseTasksListResult {
  state: TasksState;
  dispatch: Dispatch<TasksAction>;
  query: UseQueryResult<Page<Task>>;
  view: TasksView;
  setView: (view: TasksView) => void;
  board: TasksBoardData;
  calendar: TasksCalendarData;
  /** Filters and sort as a query string, the form saved views store. */
  urlKey: string;
  /** API filters without paging, for exporting every matching task. */
  exportParams: QueryParams;
}

const PER_PAGE = 10;

/** Tasks page: filters, sort and page kept in the URL, and the matching tasks as a list, board or calendar. */
export default function useTasksList(): UseTasksListResult {
  const [searchParams, setSearchParams] = useSearchParams();
  const [state, dispatch] = useReducer(tasksReducer, searchParams, stateFromSearchParams);
  const { filters, sorting, page } = state;
  const [view, setViewState] = useState<TasksView>(() => {
    const saved = readPreference('tasks-view');
    return isTasksView(saved) ? saved : 'list';
  });

  // Typing in the search box doesn't refetch on every keystroke
  const debouncedSearch = useDebounce(filters.search, 400);
  const activeFilters = filterParams({ ...filters, search: debouncedSearch });

  const urlParams: Record<string, string> = { ...activeFilters };
  if (sorting.sortBy !== DEFAULT_SORTING.sortBy || sorting.sortDir !== DEFAULT_SORTING.sortDir) {
    urlParams.sort = `${sorting.sortBy}-${sorting.sortDir}`;
  }
  if (page > 1) urlParams.page = String(page);
  const urlKey = new URLSearchParams(urlParams).toString();

  useEffect(() => {
    setSearchParams(new URLSearchParams(urlKey), { replace: true });
  }, [urlKey, setSearchParams]);

  const params = {
    page,
    per_page: PER_PAGE,
    sort_by: sorting.sortBy,
    sort_order: sorting.sortDir,
    ...activeFilters,
  };

  const query = useQuery({
    queryKey: queryKeys.tasks.list(params),
    queryFn: ({ signal }) => tasksApi.getAll(params, { signal }),
    placeholderData: keepPreviousData,
    enabled: view === 'list',
  });

  // Same filters and sort; the board makes one column per status, so the status filter doesn't apply
  const board = useTasksBoard({
    params: { ...params, status: undefined, page: undefined },
    enabled: view === 'board',
  });

  // The calendar sets its own due-date range from the visible month
  const calendar = useTasksCalendar({
    params: { ...params, due_date_from: undefined, due_date_to: undefined },
    enabled: view === 'calendar',
  });

  const setView = (next: TasksView) => {
    setViewState(next);
    writePreference('tasks-view', next);
  };

  return {
    state,
    dispatch,
    query,
    view,
    setView,
    board,
    calendar,
    urlKey,
    exportParams: { ...params, page: undefined, per_page: undefined },
  };
}
