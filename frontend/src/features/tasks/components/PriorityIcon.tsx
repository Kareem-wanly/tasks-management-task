import useTaskLookups from '@/hooks/queries/useTaskLookups';
import { cn } from '@/lib/utils';
import type { TaskPriority } from '@/types/api';

interface PriorityIconProps {
  priority: TaskPriority;
  className?: string;
}

const BAR_HEIGHTS = ['h-[7px]', 'h-[10px]', 'h-[13px]'];

/**
 * Compact priority glyph: signal bars for Bitrix's Low / Medium / High (bitrix_id 0-2),
 * a filled alert square for a danger-toned priority such as Urgent.
 */
export default function PriorityIcon({ priority, className }: PriorityIconProps) {
  const { priorityLabel, priorityTone, priorityOf } = useTaskLookups();
  const label = `${priorityLabel(priority)} priority`;

  if (priorityTone(priority) === 'danger') {
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

  const level = (priorityOf(priority)?.bitrix_id ?? 1) + 1;
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
