import type { Task } from '@/types/api';

// Server data (tasks, options) lives in React Query; this reducer only holds what the user picks.

export interface Filters {
  search: string;
  status: string;
  priority: string;
  project_id: string;
  assigned_to: string;
  due_date_from: string;
  due_date_to: string;
  overdue_only: string;
}

export type SortDir = 'asc' | 'desc';

export interface Sorting {
  sortBy: string;
  sortDir: SortDir;
}

export interface TasksState {
  filters: Filters;
  sorting: Sorting;
  page: number;
  modals: {
    isCreateOpen: boolean;
    taskToDelete: Task | null;
  };
}

export type TasksAction =
  | { type: 'SET_FILTER'; field: keyof Filters; value: string }
  | { type: 'SET_FILTERS'; payload: Partial<Filters> }
  | { type: 'RESET_FILTERS' }
  /** Applies a saved view: its filters and sort replace the current ones. */
  | { type: 'LOAD'; payload: Pick<TasksState, 'filters' | 'sorting'> }
  | { type: 'SET_SORT'; payload: Sorting }
  | { type: 'SET_PAGE'; payload: number }
  | { type: 'OPEN_CREATE_MODAL' }
  | { type: 'CLOSE_CREATE_MODAL' }
  | { type: 'OPEN_DELETE_MODAL'; payload: Task }
  | { type: 'CLOSE_DELETE_MODAL' };

export const emptyFilters: Filters = {
  search: '',
  status: '',
  priority: '',
  project_id: '',
  assigned_to: '',
  due_date_from: '',
  due_date_to: '',
  overdue_only: '',
};

export const DEFAULT_SORTING: Sorting = { sortBy: 'created_at', sortDir: 'desc' };

export const toSortDir = (value: string | undefined): SortDir => (value === 'asc' ? 'asc' : 'desc');

/** Restores filters, sort and page from the URL so they survive a reload and can be linked to. */
export function stateFromSearchParams(searchParams: URLSearchParams): TasksState {
  const [sortBy, sortDir] = (searchParams.get('sort') || '').split('-');
  return {
    filters: {
      search: searchParams.get('search') || '',
      status: searchParams.get('status') || '',
      priority: searchParams.get('priority') || '',
      project_id: searchParams.get('project_id') || '',
      assigned_to: searchParams.get('assigned_to') || '',
      due_date_from: searchParams.get('due_date_from') || '',
      due_date_to: searchParams.get('due_date_to') || '',
      overdue_only: searchParams.get('overdue') || '',
    },
    sorting: {
      sortBy: sortBy || DEFAULT_SORTING.sortBy,
      sortDir: sortDir ? toSortDir(sortDir) : DEFAULT_SORTING.sortDir,
    },
    page: parseInt(searchParams.get('page') || '1', 10) || 1,
    modals: { isCreateOpen: false, taskToDelete: null },
  };
}

/** Filter values shared by the API request and the URL. */
export function filterParams(filters: Filters): Record<string, string> {
  const params: Record<string, string> = {};
  if (filters.search.trim()) params.search = filters.search.trim();
  if (filters.status) params.status = filters.status;
  if (filters.priority) params.priority = filters.priority;
  if (filters.project_id) params.project_id = filters.project_id;
  if (filters.assigned_to) params.assigned_to = filters.assigned_to;
  if (filters.due_date_from) params.due_date_from = filters.due_date_from;
  if (filters.due_date_to) params.due_date_to = filters.due_date_to;
  if (filters.overdue_only) params.overdue = 'true';
  return params;
}

export function tasksReducer(state: TasksState, action: TasksAction): TasksState {
  switch (action.type) {
    case 'SET_FILTER':
      return { ...state, filters: { ...state.filters, [action.field]: action.value }, page: 1 };

    case 'SET_FILTERS':
      return { ...state, filters: { ...state.filters, ...action.payload }, page: 1 };

    case 'RESET_FILTERS':
      return { ...state, filters: emptyFilters, page: 1 };

    case 'LOAD':
      return { ...state, filters: action.payload.filters, sorting: action.payload.sorting, page: 1 };

    case 'SET_SORT':
      return { ...state, sorting: action.payload, page: 1 };

    case 'SET_PAGE':
      return { ...state, page: action.payload };

    case 'OPEN_CREATE_MODAL':
      return { ...state, modals: { ...state.modals, isCreateOpen: true } };

    case 'CLOSE_CREATE_MODAL':
      return { ...state, modals: { ...state.modals, isCreateOpen: false } };

    case 'OPEN_DELETE_MODAL':
      return { ...state, modals: { ...state.modals, taskToDelete: action.payload } };

    case 'CLOSE_DELETE_MODAL':
      return { ...state, modals: { ...state.modals, taskToDelete: null } };
  }
}
