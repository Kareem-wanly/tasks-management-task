import { useDroppable } from '@dnd-kit/core';
import { cn } from '@/lib/utils';
import { dayOfMonth } from '@/utils/dates';
import type { Task } from '@/types/api';
import CalendarChip from './CalendarChip';

interface CalendarDayProps {
  date: string;
  tasks: Task[];
  inMonth: boolean;
  isToday: boolean;
  isPast: boolean;
  canDrag: boolean;
  /** Opens the list filtered to this day, for days with more tasks than fit. */
  onShowDay: (date: string) => void;
}

const VISIBLE = 3;

/** One cell of the month grid: the day number, up to three tasks and a "+N more" link. */
export default function CalendarDay({ date, tasks, inMonth, isToday, isPast, canDrag, onShowDay }: CalendarDayProps) {
  const { setNodeRef, isOver } = useDroppable({ id: `day:${date}` });
  const hidden = tasks.length - VISIBLE;

  return (
    <div
      ref={setNodeRef}
      className={cn(
        'flex min-h-28 min-w-0 flex-col gap-1 border-e border-b border-border p-1.5 transition-colors',
        !inMonth && 'bg-muted/40',
        isOver && 'bg-brand-soft'
      )}
    >
      <span
        className={cn(
          'grid size-6 place-items-center self-end rounded-full text-xs tabular-nums',
          !inMonth && 'text-muted-foreground/60',
          inMonth && isPast && 'text-muted-foreground',
          isToday && 'bg-brand font-semibold text-brand-foreground'
        )}
        aria-label={isToday ? `${date}, today` : date}
      >
        {dayOfMonth(date)}
      </span>
      {tasks.slice(0, VISIBLE).map((task) => (
        <CalendarChip key={task.id} task={task} disabled={!canDrag} />
      ))}
      {hidden > 0 && (
        <button
          type="button"
          onClick={() => onShowDay(date)}
          className="rounded-sm px-1 text-start text-[11px] text-muted-foreground hover:bg-muted hover:text-foreground"
        >
          +{hidden} more
        </button>
      )}
    </div>
  );
}
