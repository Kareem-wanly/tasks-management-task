import { Link } from 'react-router-dom';
import { ArrowRightIcon, CircleCheckBigIcon } from 'lucide-react';
import DeadlineBadge from '@/components/data/DeadlineBadge';
import EmptyState from '@/components/data/EmptyState';
import ProjectMark from '@/components/data/ProjectMark';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import { PriorityIcon, TaskCompleteButton } from '@/features/tasks';
import { useAuth } from '@/lib/auth/AuthContext';
import { cn } from '@/lib/utils';
import { formatDateOnly } from '@/utils/format';
import type { Task } from '@/types/api';

interface MyWorkPanelProps {
  /** Unfinished tasks assigned to the user, soonest due first. */
  tasks: Task[];
  allHref: string;
}

const LIMIT = 7;

/** The user's next tasks, each completable in one click. */
export default function MyWorkPanel({ tasks, allHref }: MyWorkPanelProps) {
  const { can } = useAuth();
  const canComplete = can('tasks.change_status') || can('tasks.update');

  return (
    <Panel
      flush
      title="My work"
      description={tasks.length > 0 ? `${tasks.length} open, soonest due first` : undefined}
      actions={
        <Button asChild variant="ghost" size="sm">
          <Link to={allHref}>
            View all
            <ArrowRightIcon className="rtl:-scale-x-100" />
          </Link>
        </Button>
      }
    >
      {tasks.length === 0 ? (
        <EmptyState
          icon={CircleCheckBigIcon}
          title="You're all caught up"
          description="Nothing open is assigned to you."
          className="py-10"
        />
      ) : (
        <ul className="divide-y divide-border">
          {tasks.slice(0, LIMIT).map((task) => (
            <li key={task.id} className="flex items-center gap-2 px-3 py-2 transition-colors hover:bg-muted/40">
              {canComplete && <TaskCompleteButton task={task} />}
              <PriorityIcon priority={task.priority} className="shrink-0" />
              <div className="min-w-0 flex-1">
                <Link to={`/tasks/${task.id}`} className="block truncate text-[13px] font-medium hover:text-brand">
                  {task.title}
                </Link>
                {task.project && (
                  <p className="flex min-w-0 items-center gap-1 text-xs text-muted-foreground">
                    <ProjectMark projectId={task.project.id} size="xs" />
                    <span className="truncate">{task.project.title}</span>
                  </p>
                )}
              </div>
              <DeadlineBadge dueDate={task.due_date} overdue={task.is_overdue} className="hidden px-1.5 py-0 text-[10px] sm:inline-flex" />
              <span
                className={cn(
                  'w-24 shrink-0 text-end text-xs text-muted-foreground tabular-nums',
                  task.is_overdue && 'font-medium text-danger'
                )}
              >
                {formatDateOnly(task.due_date, 'No date')}
              </span>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}
