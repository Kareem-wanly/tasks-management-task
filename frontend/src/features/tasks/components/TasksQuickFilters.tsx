import type { Dispatch } from 'react';
import { AlarmClockIcon, CalendarRangeIcon, FlameIcon, UserIcon, type LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { URGENT_PRIORITY } from '@/constants/statuses';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import { cn } from '@/lib/utils';
import { daysFromNow } from '@/utils/dates';
import type { Filters, TasksAction } from '../tasksReducer';

interface TasksQuickFiltersProps {
  filters: Filters;
  userId: number | undefined;
  dispatch: Dispatch<TasksAction>;
}

interface QuickFilter {
  key: string;
  label: string;
  icon: LucideIcon;
  /** Filter values the chip sets; clicking an active chip clears them. */
  values: [keyof Filters, string][];
}

function toPayload(values: [keyof Filters, string][], clear: boolean): Partial<Filters> {
  const payload: Partial<Filters> = {};
  for (const [field, value] of values) payload[field] = clear ? '' : value;
  return payload;
}

/** One-click presets on top of the regular filters. */
export default function TasksQuickFilters({ filters, userId, dispatch }: TasksQuickFiltersProps) {
  const { priorityOf } = useTaskLookups();
  const today = daysFromNow(0);
  const mine: QuickFilter[] = userId
    ? [{ key: 'mine', label: 'My tasks', icon: UserIcon, values: [['assigned_to', String(userId)]] }]
    : [];
  const chips: QuickFilter[] = [
    ...mine,
    { key: 'overdue', label: 'Overdue', icon: AlarmClockIcon, values: [['overdue_only', 'true']] },
    {
      key: 'week',
      label: 'Due in 7 days',
      icon: CalendarRangeIcon,
      values: [
        ['due_date_from', today],
        ['due_date_to', daysFromNow(7)],
      ],
    },
  ];
  // "Urgent" is our own priority, so an admin may have removed it
  const urgent = priorityOf(URGENT_PRIORITY);
  if (urgent) chips.push({ key: 'urgent', label: urgent.name, icon: FlameIcon, values: [['priority', urgent.key]] });

  return (
    <div className="flex flex-wrap items-center gap-2" role="group" aria-label="Quick filters">
      {chips.map((chip) => {
        const active = chip.values.every(([field, value]) => filters[field] === value);
        const Icon = chip.icon;

        return (
          <Button
            key={chip.key}
            type="button"
            size="sm"
            variant="outline"
            aria-pressed={active}
            onClick={() => dispatch({ type: 'SET_FILTERS', payload: toPayload(chip.values, active) })}
            className={cn('rounded-full', active && 'border-brand/40 bg-brand-soft text-brand hover:bg-brand-soft hover:text-brand')}
          >
            <Icon />
            {chip.label}
          </Button>
        );
      })}
    </div>
  );
}
