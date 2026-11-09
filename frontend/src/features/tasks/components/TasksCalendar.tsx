import { useState } from 'react';
import {
  DndContext,
  DragOverlay,
  KeyboardSensor,
  MouseSensor,
  TouchSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type UniqueIdentifier,
} from '@dnd-kit/core';
import { ChevronLeftIcon, ChevronRightIcon, InfoIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/lib/auth/AuthContext';
import { addMonths, daysFromNow, monthLabel, startOfMonth, weekdayLabels } from '@/utils/dates';
import type { Task } from '@/types/api';
import type { TasksCalendarData } from '../hooks/useTasksCalendar';
import CalendarChipCard from './CalendarChipCard';
import CalendarDay from './CalendarDay';

interface TasksCalendarProps {
  calendar: TasksCalendarData;
  /** Opens the list filtered to one day. */
  onShowDay: (date: string) => void;
}

const DAY_PREFIX = 'day:';
const toDay = (id: UniqueIdentifier | undefined): string | null =>
  typeof id === 'string' && id.startsWith(DAY_PREFIX) ? id.slice(DAY_PREFIX.length) : null;

/** Month calendar of due dates; drag a task to another day to reschedule it (Undo in the toast). */
export default function TasksCalendar({ calendar, onShowDay }: TasksCalendarProps) {
  const { can } = useAuth();
  const canDrag = can('tasks.update');
  const [active, setActive] = useState<Task | null>(null);
  const { month, setMonth, days, tasksByDay, total, loaded, isError, move } = calendar;
  const today = daysFromNow(0);

  const sensors = useSensors(
    useSensor(MouseSensor, { activationConstraint: { distance: 6 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 6 } }),
    useSensor(KeyboardSensor)
  );
  const findTask = (id: UniqueIdentifier) => [...tasksByDay.values()].flat().find((t) => t.id === id);

  const handleDragEnd = ({ active: dragged, over }: DragEndEvent) => {
    setActive(null);
    const task = findTask(dragged.id);
    const day = toDay(over?.id);
    if (task && day) move(task, day);
  };

  return (
    <div className="space-y-3 p-3">
      <div className="flex flex-wrap items-center gap-2">
        <h2 className="me-auto text-[15px] font-semibold">{monthLabel(month)}</h2>
        {loaded < total && (
          <span className="flex items-center gap-1.5 text-xs text-warning">
            <InfoIcon className="size-3.5" />
            Showing the first {loaded} of {total} tasks; narrow the filters to see all.
          </span>
        )}
        {isError && <span className="text-xs text-destructive">Couldn't load this month.</span>}
        <Button variant="outline" size="sm" onClick={() => setMonth(startOfMonth(today))}>
          Today
        </Button>
        <div className="flex">
          <Button variant="ghost" size="icon-sm" onClick={() => setMonth(addMonths(month, -1))} aria-label="Previous month">
            <ChevronLeftIcon className="rtl:-scale-x-100" />
          </Button>
          <Button variant="ghost" size="icon-sm" onClick={() => setMonth(addMonths(month, 1))} aria-label="Next month">
            <ChevronRightIcon className="rtl:-scale-x-100" />
          </Button>
        </div>
      </div>

      <DndContext
        sensors={sensors}
        onDragStart={({ active: dragged }) => setActive(findTask(dragged.id) ?? null)}
        onDragEnd={handleDragEnd}
        onDragCancel={() => setActive(null)}
      >
        <div className="overflow-x-auto">
          <div className="min-w-[44rem] overflow-hidden rounded-lg border-s border-t border-border">
            <div className="grid grid-cols-7 bg-muted/60">
              {weekdayLabels().map((day) => (
                <div key={day} className="border-e border-b border-border px-2 py-1.5 text-xs font-medium text-muted-foreground">
                  {day}
                </div>
              ))}
            </div>
            <div className="grid grid-cols-7">
              {days.map((date) => (
                <CalendarDay
                  key={date}
                  date={date}
                  tasks={tasksByDay.get(date) ?? []}
                  inMonth={date.slice(0, 7) === month.slice(0, 7)}
                  isToday={date === today}
                  isPast={date < today}
                  canDrag={canDrag}
                  onShowDay={onShowDay}
                />
              ))}
            </div>
          </div>
        </div>
        <DragOverlay dropAnimation={null}>{active && <CalendarChipCard task={active} overlay />}</DragOverlay>
      </DndContext>

      <p className="text-xs text-muted-foreground">Tasks without a due date don't appear on the calendar.</p>
    </div>
  );
}
