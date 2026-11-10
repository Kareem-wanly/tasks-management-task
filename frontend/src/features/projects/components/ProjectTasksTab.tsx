import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowUpRightIcon, PlusIcon, SearchIcon } from 'lucide-react';
import ViewToggle from '@/components/data/ViewToggle';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import ErrorState from '@/components/ui/ErrorState';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import { TasksBoard, TasksBulkBar, TasksCalendar, TasksTable } from '@/features/tasks';
import type { ProjectTasksList } from '../hooks/useProjectTasks';

interface ProjectTasksTabProps {
  projectId: number;
  list: ProjectTasksList;
  /** False when the user can't create tasks or the project is archived. */
  canCreateTasks: boolean;
  onCreateTask: () => void;
}

// Select items can't use '' as a value
const ALL = 'all';

export default function ProjectTasksTab({ projectId, list, canCreateTasks, onCreateTask }: ProjectTasksTabProps) {
  const { view, setView, query, board, calendar, search, setSearch, status, setStatus, page, setPage } = list;
  const navigate = useNavigate();
  const { statusOptions } = useTaskLookups();
  const [selectedIds, setSelectedIds] = useState<Set<number>>(() => new Set());
  const tasks = query.data?.items ?? [];
  const selected = view === 'list' ? tasks.filter((t) => selectedIds.has(t.id)) : [];
  const filtered = search !== '' || status !== '';

  return (
    <Panel
      flush
      title="Project tasks"
      actions={
        <>
          <Button size="sm" variant="ghost" asChild>
            <Link to={`/tasks?project_id=${projectId}`}>
              All filters
              <ArrowUpRightIcon className="rtl:-scale-x-100" />
            </Link>
          </Button>
          {canCreateTasks && (
            <Button size="sm" onClick={onCreateTask}>
              <PlusIcon />
              Create task
            </Button>
          )}
        </>
      }
    >
      <div className="flex flex-wrap gap-2 border-b border-border/60 px-6 py-3">
        <div className="relative min-w-48 flex-1">
          <SearchIcon className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            type="search"
            data-page-search
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search this project's tasks..."
            aria-label="Search project tasks"
            className="ps-9"
          />
        </div>
        {view !== 'board' && (
          <Select value={status || ALL} onValueChange={(v) => setStatus(v === ALL ? '' : v)}>
            <SelectTrigger className="w-40" aria-label="Filter by status">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>All statuses</SelectItem>
              {statusOptions.map((s) => (
                <SelectItem key={s.value} value={s.value}>
                  {s.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
        <ViewToggle value={view} onChange={setView} />
      </div>

      {view === 'board' ? (
        <TasksBoard board={board} onShowAll={(s) => navigate(`/tasks?project_id=${projectId}&status=${s}`)} />
      ) : view === 'calendar' ? (
        <TasksCalendar
          calendar={calendar}
          onShowDay={(day) => navigate(`/tasks?project_id=${projectId}&due_date_from=${day}&due_date_to=${day}`)}
        />
      ) : query.isError ? (
        <ErrorState
          title="Failed to load tasks"
          message={query.error}
          onRetry={() => query.refetch()}
          className="rounded-none border-0 shadow-none"
        />
      ) : (
        <TasksTable
          framed={false}
          showProject={false}
          tasks={tasks}
          selectedIds={selectedIds}
          onSelectionChange={setSelectedIds}
          loading={query.isPending}
          page={page}
          lastPage={query.data?.lastPage ?? 1}
          total={query.data?.total ?? 0}
          onPageChange={setPage}
          emptyMessage={filtered ? 'No tasks match these filters' : 'No tasks in this project yet'}
          emptyDescription={
            filtered ? 'Try another search or status.' : canCreateTasks ? 'Use "Create task" to add the first one.' : undefined
          }
        />
      )}
      {selected.length > 0 && <TasksBulkBar selected={selected} onClear={() => setSelectedIds(new Set())} />}
    </Panel>
  );
}
