import {
  ChevronsLeftRightIcon,
  CircleCheckIcon,
  CircleDashedIcon,
  CircleDotIcon,
  CircleEllipsisIcon,
  EllipsisIcon,
  type LucideIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { TASK_STATUS_LABELS } from '@/constants/statuses';
import { cn } from '@/lib/utils';
import type { TaskStatus } from '@/types/api';

interface BoardColumnHeaderProps {
  status: TaskStatus;
  count: number;
  /** Work-in-progress limit; the count turns red above it. */
  limit?: number;
  collapsed: boolean;
  onToggleCollapsed: () => void;
  onLimitChange: (limit: number | undefined) => void;
}

const STATUS_ICONS: Record<TaskStatus, { icon: LucideIcon; color: string }> = {
  todo: { icon: CircleDashedIcon, color: 'text-muted-foreground' },
  in_progress: { icon: CircleDotIcon, color: 'text-info' },
  review: { icon: CircleEllipsisIcon, color: 'text-brand' },
  completed: { icon: CircleCheckIcon, color: 'text-success' },
};

const LIMITS = [3, 5, 8, 12];

/** Status, count (against its WIP limit) and a menu to collapse the column or set the limit. */
export default function BoardColumnHeader({
  status,
  count,
  limit,
  collapsed,
  onToggleCollapsed,
  onLimitChange,
}: BoardColumnHeaderProps) {
  const label = TASK_STATUS_LABELS[status];
  const { icon: Icon, color } = STATUS_ICONS[status];
  const over = limit !== undefined && count > limit;

  if (collapsed) {
    return (
      <button
        type="button"
        onClick={onToggleCollapsed}
        title={`Expand ${label}`}
        className="flex w-full flex-col items-center gap-2 rounded-md py-2 hover:bg-muted"
      >
        <Icon className={cn('size-3.5', color)} />
        <span className="text-xs text-muted-foreground tabular-nums">{count}</span>
        <span className="text-[13px] font-semibold [writing-mode:vertical-rl]">{label}</span>
      </button>
    );
  }

  return (
    <div className="flex items-center gap-2 px-1.5 py-1">
      <Icon className={cn('size-3.5 shrink-0', color)} />
      <h3 className="truncate text-[13px] font-semibold">{label}</h3>
      <span
        className={cn('text-xs tabular-nums', over ? 'font-semibold text-danger' : 'text-muted-foreground')}
        title={over ? `Over the limit of ${limit}` : undefined}
      >
        {count}
        {limit !== undefined && ` / ${limit}`}
      </span>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="icon-xs" className="ms-auto" aria-label={`${label} column options`}>
            <EllipsisIcon />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-52">
          <DropdownMenuItem onSelect={onToggleCollapsed}>
            <ChevronsLeftRightIcon />
            Collapse column
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuLabel>Work-in-progress limit</DropdownMenuLabel>
          {LIMITS.map((value) => (
            <DropdownMenuItem key={value} onSelect={() => onLimitChange(value)}>
              {value} tasks
              {limit === value && <span className="ms-auto text-xs text-brand">Current</span>}
            </DropdownMenuItem>
          ))}
          <DropdownMenuItem onSelect={() => onLimitChange(undefined)} disabled={limit === undefined}>
            No limit
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}
