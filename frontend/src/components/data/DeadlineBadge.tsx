import StatusBadge from '@/components/data/StatusBadge';
import type { Tone } from '@/constants/statuses';
import { daysUntil } from '@/utils/dates';

interface DeadlineBadgeProps {
  dueDate: string | null | undefined;
  /** Prefer the server flag (`task.is_overdue`) when there is one. */
  overdue: boolean;
  /** Finished items get no badge. */
  done?: boolean;
  /** Days ahead for which an upcoming deadline is shown. */
  horizon?: number;
  className?: string;
}

const plural = (n: number, word: string): string => `${n} ${word}${n === 1 ? '' : 's'}`;

/** Relative deadline hint: "3 days overdue", "Due today", "Due in 5 days". */
export default function DeadlineBadge({ dueDate, overdue, done = false, horizon = 7, className }: DeadlineBadgeProps) {
  const days = daysUntil(dueDate);
  if (done || days === null) return null;

  let tone: Tone;
  let label: string;
  if (overdue) {
    tone = 'danger';
    label = days < 0 ? `${plural(-days, 'day')} overdue` : 'Overdue';
  } else if (days === 0) {
    tone = 'warning';
    label = 'Due today';
  } else if (days > 0 && days <= horizon) {
    tone = days <= 2 ? 'warning' : 'info';
    label = days === 1 ? 'Due tomorrow' : `Due in ${plural(days, 'day')}`;
  } else {
    return null;
  }

  return (
    <StatusBadge tone={tone} plain className={className ?? 'px-2 py-0.5'}>
      {label}
    </StatusBadge>
  );
}
