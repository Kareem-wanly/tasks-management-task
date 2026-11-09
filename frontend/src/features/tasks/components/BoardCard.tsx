import { useDraggable } from '@dnd-kit/core';
import { cn } from '@/lib/utils';
import type { Task } from '@/types/api';
import TaskCard from './TaskCard';

interface BoardCardProps {
  task: Task;
  disabled: boolean;
  showProject: boolean;
}

/** Makes a TaskCard draggable. The moving copy is drawn by the board's DragOverlay. */
export default function BoardCard({ task, disabled, showProject }: BoardCardProps) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: task.id, disabled });

  return (
    <div
      ref={setNodeRef}
      {...listeners}
      {...attributes}
      aria-label={disabled ? task.title : `${task.title}. Press space to move it to another column.`}
      className={cn(
        'rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring/40',
        !disabled && 'cursor-grab active:cursor-grabbing'
      )}
    >
      <TaskCard task={task} dragging={isDragging} showProject={showProject} />
    </div>
  );
}
