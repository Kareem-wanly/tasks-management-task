import type { Announcements, UniqueIdentifier } from '@dnd-kit/core';
import type { Task, TaskStatus } from '@/types/api';

/** Screen reader messages while dragging, naming the task and the column instead of raw ids. */
export function boardAnnouncements(
  findTask: (id: UniqueIdentifier) => Task | undefined,
  statusLabel: (status: TaskStatus) => string
): Announcements {
  const titleOf = (id: UniqueIdentifier) => findTask(id)?.title ?? 'Task';
  // Cell ids are "<lane>|<status>"
  const columnOf = (id: UniqueIdentifier | undefined) => {
    const status = typeof id === 'string' ? id.split('|')[1] : undefined;
    return status ? statusLabel(status) : 'no column';
  };

  return {
    onDragStart: ({ active }) => `Picked up ${titleOf(active.id)}.`,
    onDragOver: ({ active, over }) => `${titleOf(active.id)} is over ${columnOf(over?.id)}.`,
    onDragEnd: ({ active, over }) =>
      over ? `${titleOf(active.id)} moved to ${columnOf(over.id)}.` : `${titleOf(active.id)} was dropped.`,
    onDragCancel: ({ active }) => `Moving ${titleOf(active.id)} was cancelled.`,
  };
}
