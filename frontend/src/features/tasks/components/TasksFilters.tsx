import type { Dispatch } from 'react';
import { RotateCcwIcon, SearchIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import type { ProjectOption, UserOption } from '@/types/api';
import { toSortDir, type Filters, type Sorting, type TasksAction } from '../tasksReducer';
import FilterSelect, { type FilterOption } from './FilterSelect';

interface TasksFiltersProps {
  filters: Filters;
  sorting: Sorting;
  projects: ProjectOption[];
  users: UserOption[];
  dispatch: Dispatch<TasksAction>;
  /** The board shows every status as a column, so its filter is hidden there. */
  hideStatus?: boolean;
}

const DEADLINE_OPTIONS: FilterOption[] = [{ value: 'true', label: 'Overdue only' }];
const SORT_OPTIONS: FilterOption[] = [
  { value: 'created_at-desc', label: 'Newest first' },
  { value: 'created_at-asc', label: 'Oldest first' },
  { value: 'due_date-asc', label: 'Due date (earliest)' },
  { value: 'due_date-desc', label: 'Due date (latest)' },
];

export default function TasksFilters({ filters, sorting, projects, users, dispatch, hideStatus = false }: TasksFiltersProps) {
  const { statusOptions, priorityOptions } = useTaskLookups();
  const setFilter = (field: keyof Filters, value: string) => dispatch({ type: 'SET_FILTER', field, value });

  const hasActiveFilters = Object.values(filters).some(Boolean);

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="relative min-w-60 flex-1">
        <SearchIcon className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="search"
          data-page-search
          id="task-search-input"
          name="search"
          placeholder="Search by title..."
          aria-label="Search tasks by title"
          value={filters.search}
          onChange={(e) => setFilter('search', e.target.value)}
          className="ps-9"
        />
      </div>

      <FilterSelect
        ariaLabel="Filter by project"
        allLabel="All projects"
        value={filters.project_id}
        onChange={(v) => setFilter('project_id', v)}
        options={projects.map((p) => ({ value: String(p.id), label: p.title }))}
      />

      {users.length > 0 && (
        <FilterSelect
          ariaLabel="Filter by assignee"
          allLabel="All assignees"
          value={filters.assigned_to}
          onChange={(v) => setFilter('assigned_to', v)}
          options={users.map((u) => ({ value: String(u.id), label: u.name }))}
        />
      )}

      {!hideStatus && (
        <FilterSelect
          ariaLabel="Filter by status"
          allLabel="All statuses"
          value={filters.status}
          onChange={(v) => setFilter('status', v)}
          options={statusOptions}
        />
      )}

      <FilterSelect
        ariaLabel="Filter by priority"
        allLabel="All priorities"
        value={filters.priority}
        onChange={(v) => setFilter('priority', v)}
        options={priorityOptions}
      />

      <Input
        type="date"
        id="task-due-from-filter"
        name="due_date_from"
        value={filters.due_date_from}
        max={filters.due_date_to || undefined}
        onChange={(e) => setFilter('due_date_from', e.target.value)}
        className="w-40"
        title="Due date from"
        aria-label="Due date from"
      />

      <Input
        type="date"
        id="task-due-to-filter"
        name="due_date_to"
        value={filters.due_date_to}
        min={filters.due_date_from || undefined}
        onChange={(e) => setFilter('due_date_to', e.target.value)}
        className="w-40"
        title="Due date to"
        aria-label="Due date to"
      />

      <FilterSelect
        ariaLabel="Filter by deadline"
        allLabel="All deadlines"
        value={filters.overdue_only}
        onChange={(v) => setFilter('overdue_only', v)}
        options={DEADLINE_OPTIONS}
      />

      <FilterSelect
        ariaLabel="Sort tasks"
        value={`${sorting.sortBy}-${sorting.sortDir}`}
        onChange={(v) => {
          const [sortBy, dir] = v.split('-');
          dispatch({ type: 'SET_SORT', payload: { sortBy, sortDir: toSortDir(dir) } });
        }}
        options={SORT_OPTIONS}
        className="w-44"
      />

      {hasActiveFilters && (
        <Button type="button" variant="ghost" size="sm" onClick={() => dispatch({ type: 'RESET_FILTERS' })}>
          <RotateCcwIcon />
          Reset
        </Button>
      )}
    </div>
  );
}
