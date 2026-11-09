import { Link } from 'react-router-dom';
import { cn } from '@/lib/utils';
import type { Task } from '@/types/api';
import PriorityIcon from './PriorityIcon';

interface CalendarChipCardProps {
  task: Task;
  dragging?: boolean;
  /** The copy that follows the pointer. */
  overlay?: boolean;
}

/** How a task looks inside a calendar day. */
export default function CalendarChipCard({ task, dragging = false, overlay = false }: CalendarChipCardProps) {
  const done = task.status === 'completed';

  return (
    <div
      className={cn(
        'flex items-center gap-1.5 rounded-sm border border-border bg-card px-1.5 py-1 text-[11px] leading-4',
        dragging && 'opacity-40',
        task.is_overdue && !done && 'border-danger/40 bg-danger-soft',
        overlay && 'w-44 cursor-grabbing shadow-lift'
      )}
    >
      <PriorityIcon priority={task.priority} className="h-3 shrink-0 scale-75" />
      <Link
        to={`/tasks/${task.id}`}
        // The browser's own link dragging would fight the calendar's drag and drop
        draggable={false}
        className={cn('min-w-0 flex-1 truncate font-medium hover:text-brand', done && 'text-muted-foreground line-through')}
      >
        {task.title}
      </Link>
    </div>
  );
}
