import { useDraggable } from '@dnd-kit/core';
import { cn } from '@/lib/utils';
import type { Task } from '@/types/api';
import CalendarChipCard from './CalendarChipCard';

interface CalendarChipProps {
  task: Task;
  disabled: boolean;
}

/** Makes a calendar task draggable; drop it on another day to change its due date. */
export default function CalendarChip({ task, disabled }: CalendarChipProps) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: task.id, disabled });

  return (
    <div
      ref={setNodeRef}
      {...listeners}
      {...attributes}
      aria-label={disabled ? task.title : `${task.title}. Press space to move it to another day.`}
      className={cn(
        'rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/40',
        !disabled && 'cursor-grab active:cursor-grabbing'
      )}
    >
      <CalendarChipCard task={task} dragging={isDragging} />
    </div>
  );
}
