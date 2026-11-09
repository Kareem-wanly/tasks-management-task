import { CalendarDaysIcon, KanbanIcon, ListIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type TasksView = 'list' | 'board' | 'calendar';

export const TASKS_VIEWS: readonly TasksView[] = ['list', 'board', 'calendar'];

export function isTasksView(value: unknown): value is TasksView {
  return TASKS_VIEWS.some((view) => view === value);
}

interface ViewToggleProps {
  value: TasksView;
  onChange: (view: TasksView) => void;
  className?: string;
}

const VIEWS = [
  { value: 'list', label: 'List view', icon: ListIcon },
  { value: 'board', label: 'Board view', icon: KanbanIcon },
  { value: 'calendar', label: 'Calendar view', icon: CalendarDaysIcon },
] as const;

/** Segmented List / Board / Calendar switch. */
export default function ViewToggle({ value, onChange, className }: ViewToggleProps) {
  return (
    <div className={cn('inline-flex rounded-lg border bg-card p-0.5', className)} role="group" aria-label="View">
      {VIEWS.map(({ value: view, label, icon: Icon }) => (
        <Button
          key={view}
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label={label}
          title={label}
          aria-pressed={value === view}
          onClick={() => onChange(view)}
          className={cn('text-muted-foreground', value === view && 'bg-muted text-foreground')}
        >
          <Icon />
        </Button>
      ))}
    </div>
  );
}
