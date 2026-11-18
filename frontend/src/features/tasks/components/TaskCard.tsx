import { Link } from 'react-router-dom';
import DeadlineBadge from '@/components/data/DeadlineBadge';
import ProjectMark from '@/components/data/ProjectMark';
import UserAvatar from '@/components/data/UserAvatar';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import { cn } from '@/lib/utils';
import { formatDateOnly } from '@/utils/format';
import type { Task } from '@/types/api';
import PriorityIcon from './PriorityIcon';

interface TaskCardProps {
  task: Task;
  /** The original card while its copy is being dragged. */
  dragging?: boolean;
  /** The copy that follows the pointer. */
  overlay?: boolean;
  /** Name the project, for boards that mix projects. */
  showProject?: boolean;
}

/** A compact task on the board: id and priority, title, then project, deadline and assignee. */
export default function TaskCard({ task, dragging = false, overlay = false, showProject = false }: TaskCardProps) {
  const { isFinal } = useTaskLookups();
  const done = isFinal(task.status);
  const overdue = task.is_overdue && !done;

  return (
    <div
      className={cn(
        'space-y-2 rounded-lg border border-border bg-card p-2.5 transition-[opacity,border-color] duration-150 hover:border-foreground/20',
        dragging && 'opacity-40',
        overlay && 'cursor-grabbing border-brand/50 shadow-lift'
      )}
    >
      <div className="flex items-center justify-between gap-2">
        <span className="font-mono text-[11px] text-muted-foreground">#{task.id}</span>
        <PriorityIcon priority={task.priority} />
      </div>

      <Link
        to={`/tasks/${task.id}`}
        // The browser's own link dragging would fight the board's drag and drop
        draggable={false}
        className={cn(
          'line-clamp-2 text-[13px] leading-5 font-medium transition-colors duration-150 hover:text-brand',
          done && 'text-muted-foreground line-through decoration-muted-foreground/50'
        )}
      >
        {task.title}
      </Link>

      {showProject && task.project && (
        <p className="flex min-w-0 items-center gap-1 text-[11px] text-muted-foreground">
          <ProjectMark projectId={task.project.id} size="xs" />
          <span className="truncate">{task.project.title}</span>
        </p>
      )}

      <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
        {task.due_date ? (
          <span className={cn('tabular-nums', overdue && 'font-medium text-danger')}>{formatDateOnly(task.due_date)}</span>
        ) : (
          <span>No date</span>
        )}
        <DeadlineBadge dueDate={task.due_date} overdue={task.is_overdue} done={done} className="px-1.5 py-0 text-[10px]" />
        {task.assignee ? (
          <UserAvatar user={task.assignee} size="xs" className="ms-auto" />
        ) : (
          <span
            aria-label="Unassigned"
            title="Unassigned"
            className="ms-auto size-5 shrink-0 rounded-full border border-dashed border-muted-foreground/50"
          />
        )}
      </div>
    </div>
  );
}
