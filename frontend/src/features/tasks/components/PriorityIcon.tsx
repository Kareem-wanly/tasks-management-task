import { TASK_PRIORITY_LABELS } from '@/constants/statuses';
import { cn } from '@/lib/utils';
import type { TaskPriority } from '@/types/api';

interface PriorityIconProps {
  priority: TaskPriority;
  className?: string;
}

const LEVEL: Record<Exclude<TaskPriority, 'urgent'>, number> = { low: 1, medium: 2, high: 3 };
const BAR_HEIGHTS = ['h-[7px]', 'h-[10px]', 'h-[13px]'];

/** Compact priority glyph: signal bars for low → high, a filled alert square for urgent. */
export default function PriorityIcon({ priority, className }: PriorityIconProps) {
  const label = `${TASK_PRIORITY_LABELS[priority]} priority`;

  if (priority === 'urgent') {
    return (
      <span
        role="img"
        aria-label={label}
        title={label}
        className={cn('grid size-4 place-items-center rounded-sm bg-danger text-[10px] leading-none font-bold text-white', className)}
      >
        !
      </span>
    );
  }

  const level = LEVEL[priority];
  return (
    <span role="img" aria-label={label} title={label} className={cn('inline-flex h-4 items-end gap-0.5', className)}>
      {BAR_HEIGHTS.map((height, index) => (
        <span
          key={height}
          className={cn('w-[3px] rounded-[1px]', height, index < level ? 'bg-foreground/70' : 'bg-foreground/15')}
        />
      ))}
    </span>
  );
}
