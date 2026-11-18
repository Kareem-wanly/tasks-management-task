import {
  ChevronsLeftRightIcon,
  CircleCheckIcon,
  CircleDashedIcon,
  CircleDotIcon,
  CircleEllipsisIcon,
  CirclePauseIcon,
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
import type { Tone } from '@/constants/statuses';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
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

// Built-in statuses get their own icon; any other one is drawn by whether it closes the task
const STATUS_ICONS: Partial<Record<TaskStatus, LucideIcon>> = {
  todo: CircleDashedIcon,
  in_progress: CircleDotIcon,
  review: CircleEllipsisIcon,
  completed: CircleCheckIcon,
  deferred: CirclePauseIcon,
};

const TONE_TEXT: Record<Tone, string> = {
  neutral: 'text-muted-foreground',
  info: 'text-info',
  brand: 'text-brand',
  success: 'text-success',
  warning: 'text-warning',
  danger: 'text-danger',
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
  const { statusLabel, statusTone, isFinal } = useTaskLookups();
  const label = statusLabel(status);
  const Icon = STATUS_ICONS[status] ?? (isFinal(status) ? CircleCheckIcon : CircleDashedIcon);
  const color = TONE_TEXT[statusTone(status)];
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
