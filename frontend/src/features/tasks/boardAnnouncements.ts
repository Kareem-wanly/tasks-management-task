import type { Announcements, UniqueIdentifier } from '@dnd-kit/core';
import { TASK_STATUSES, TASK_STATUS_LABELS } from '@/constants/statuses';
import type { Task } from '@/types/api';

/** Screen reader messages while dragging, naming the task and the column instead of raw ids. */
export function boardAnnouncements(findTask: (id: UniqueIdentifier) => Task | undefined): Announcements {
  const titleOf = (id: UniqueIdentifier) => findTask(id)?.title ?? 'Task';
  const columnOf = (id: UniqueIdentifier | undefined) => {
    const status = TASK_STATUSES.find((s) => typeof id === 'string' && id.endsWith(`|${s}`));
    return status ? TASK_STATUS_LABELS[status] : 'no column';
  };

  return {
    onDragStart: ({ active }) => `Picked up ${titleOf(active.id)}.`,
    onDragOver: ({ active, over }) => `${titleOf(active.id)} is over ${columnOf(over?.id)}.`,
    onDragEnd: ({ active, over }) =>
      over ? `${titleOf(active.id)} moved to ${columnOf(over.id)}.` : `${titleOf(active.id)} was dropped.`,
    onDragCancel: ({ active }) => `Moving ${titleOf(active.id)} was cancelled.`,
  };
}
