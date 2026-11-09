import type { ReactNode } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Trash2Icon } from 'lucide-react';
import DataTable, { type DataTableColumn } from '@/components/data/DataTable';
import DeadlineBadge from '@/components/data/DeadlineBadge';
import Pagination from '@/components/data/Pagination';
import ProjectMark from '@/components/data/ProjectMark';
import StatusBadge from '@/components/data/StatusBadge';
import UserAvatar from '@/components/data/UserAvatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { STATUS_TONES, TASK_STATUS_LABELS, isLiveStatus } from '@/constants/statuses';
import { useAuth } from '@/lib/auth/AuthContext';
import { cn } from '@/lib/utils';
import { formatDateOnly } from '@/utils/format';
import type { Task } from '@/types/api';
import PriorityIcon from './PriorityIcon';
import TaskCompleteButton from './TaskCompleteButton';
import TasksMobileList from './TasksMobileList';

interface TasksTableProps {
  tasks: Task[];
  loading: boolean;
  page: number;
  lastPage: number;
  total: number;
  onPageChange: (page: number) => void;
  onDelete?: (task: Task) => void;
  /** Turns on the checkbox column; the parent owns the selection. */
  selectedIds?: ReadonlySet<number>;
  onSelectionChange?: (ids: Set<number>) => void;
  /** Hide the project column when the table already sits inside a project. */
  showProject?: boolean;
  /** Set false inside a flush `Panel`. */
  framed?: boolean;
  emptyMessage?: string;
  emptyDescription?: ReactNode;
}

// Rows open the task on click; controls inside a row must not
const stop = { onClick: (e: { stopPropagation: () => void }) => e.stopPropagation() };

export default function TasksTable({
  tasks,
  loading,
  page,
  lastPage,
  total,
  onPageChange,
  onDelete,
  selectedIds,
  onSelectionChange,
  showProject = true,
  framed = true,
  emptyMessage = 'No tasks found',
  emptyDescription = 'No tasks match the selected filters.',
}: TasksTableProps) {
  const navigate = useNavigate();
  const { can } = useAuth();
  const canChangeStatus = can('tasks.change_status') || can('tasks.update');
  const columns: DataTableColumn<Task>[] = [];

  if (selectedIds && onSelectionChange) {
    const selectedOnPage = tasks.filter((t) => selectedIds.has(t.id)).length;
    const toggleAll = () => {
      const next = new Set(selectedIds);
      for (const t of tasks) {
        if (selectedOnPage === tasks.length) next.delete(t.id);
        else next.add(t.id);
      }
      onSelectionChange(next);
    };
    const toggle = (id: number) => {
      const next = new Set(selectedIds);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      onSelectionChange(next);
    };
    columns.push({
      key: 'select',
      className: 'w-9 pe-0',
      header: (
        <Checkbox
          aria-label="Select all tasks on this page"
          disabled={tasks.length === 0}
          checked={selectedOnPage === 0 ? false : selectedOnPage === tasks.length ? true : 'indeterminate'}
          onCheckedChange={toggleAll}
        />
      ),
      cell: (task) => (
        <span {...stop} className="flex">
          <Checkbox aria-label={`Select ${task.title}`} checked={selectedIds.has(task.id)} onCheckedChange={() => toggle(task.id)} />
        </span>
      ),
    });
  }

  if (canChangeStatus) {
    columns.push({
      key: 'complete',
      header: <span className="sr-only">Complete</span>,
      className: 'w-9 px-0',
      cell: (task) => <TaskCompleteButton task={task} />,
    });
  }

  columns.push({
    key: 'task',
    header: 'Task',
    cell: (task) => (
      <div className="flex max-w-96 min-w-48 items-center gap-2">
        <PriorityIcon priority={task.priority} className="shrink-0" />
        <div className="min-w-0">
          <Link
            to={`/tasks/${task.id}`}
            {...stop}
            className={cn(
              'block truncate font-medium transition-colors duration-150 hover:text-brand',
              task.status === 'completed' && 'text-muted-foreground line-through decoration-muted-foreground/50'
            )}
          >
            {task.title}
          </Link>
          {task.description && <p className="truncate text-xs text-muted-foreground">{task.description}</p>}
        </div>
      </div>
    ),
  });

  if (showProject) {
    columns.push({
      key: 'project',
      header: 'Project',
      cell: (task) =>
        task.project ? (
          <Link
            to={`/projects/${task.project.id}`}
            {...stop}
            className="flex max-w-48 items-center gap-1.5 text-muted-foreground hover:text-brand"
          >
            <ProjectMark projectId={task.project.id} size="xs" />
            <span className="truncate">{task.project.title}</span>
          </Link>
        ) : (
          <span className="text-muted-foreground">No project</span>
        ),
    });
  }

  columns.push(
    {
      key: 'assignee',
      header: 'Assignee',
      cell: (task) =>
        task.assignee ? (
          <div className="flex items-center gap-2">
            <UserAvatar user={task.assignee} />
            <span className="whitespace-nowrap">{task.assignee.name}</span>
          </div>
        ) : (
          <span className="text-muted-foreground">Unassigned</span>
        ),
    },
    {
      key: 'status',
      header: 'Status',
      cell: (task) => (
        <StatusBadge tone={STATUS_TONES[task.status]} live={isLiveStatus(task.status)}>
          {TASK_STATUS_LABELS[task.status]}
        </StatusBadge>
      ),
    },
    {
      key: 'due',
      header: 'Due date',
      cell: (task) =>
        task.due_date ? (
          <div className="flex items-center gap-2 whitespace-nowrap">
            <span className={cn('tabular-nums', task.is_overdue && 'font-medium text-danger')}>
              {formatDateOnly(task.due_date)}
            </span>
            <DeadlineBadge dueDate={task.due_date} overdue={task.is_overdue} done={task.status === 'completed'} />
          </div>
        ) : (
          <span className="text-muted-foreground">No date</span>
        ),
    }
  );

  if (onDelete && can('tasks.delete')) {
    columns.push({
      key: 'actions',
      header: <span className="sr-only">Actions</span>,
      className: 'w-12 text-end',
      cell: (task) => (
        <div className="flex justify-end" {...stop}>
          <Button variant="ghost" size="icon-sm" onClick={() => onDelete(task)} aria-label={`Delete ${task.title}`}>
            <Trash2Icon className="text-destructive" />
          </Button>
        </div>
      ),
    });
  }

  return (
    <div className="space-y-4">
      <div className={cn('md:hidden', !framed && 'p-3')}>
        <TasksMobileList
          tasks={tasks}
          loading={loading}
          canChangeStatus={canChangeStatus}
          showProject={showProject}
          emptyMessage={emptyMessage}
        />
      </div>
      <div className="hidden md:block">
        <DataTable
          framed={framed}
          columns={columns}
          rows={tasks}
          getRowKey={(task) => task.id}
          isLoading={loading}
          emptyMessage={emptyMessage}
          emptyDescription={emptyDescription}
          onRowClick={(task) => navigate(`/tasks/${task.id}`)}
        />
      </div>
      {lastPage > 1 && (
        <div className={cn(!framed && 'border-t border-border px-5 pb-4')}>
          <Pagination page={page} lastPage={lastPage} total={total} itemLabel="tasks" onPageChange={onPageChange} />
        </div>
      )}
    </div>
  );
}
