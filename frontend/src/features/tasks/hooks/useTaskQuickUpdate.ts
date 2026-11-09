import { useQueryClient } from '@tanstack/react-query';
import tasksApi, { type TaskInput } from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import useApiMutation from '@/hooks/useApiMutation';
import { showToast, showUndoToast } from '@/lib/toast/ToastContext';
import type { Task } from '@/types/api';
import { toDateOnly } from '@/utils/dates';
import { formatDateOnly } from '@/utils/format';

interface QuickUpdate {
  taskId: number;
  changes: Partial<TaskInput>;
  /** Toast text; omit for silent saves (e.g. ticking a checklist item). */
  message?: string;
  /** Values that restore the task; adds an Undo button to the toast. */
  revert?: Partial<TaskInput>;
}

export interface TaskQuickUpdate {
  /** Saves one or more fields in place (inline editing), with Undo. */
  update: (task: Task, changes: Partial<TaskInput>, message?: string) => void;
  extend: (task: Task, dueDate: string) => void;
  assignTo: (task: Task, userId: number | null, name?: string) => void;
  isPending: boolean;
}

/** The fields of a change that map 1:1 onto the cached task, for an instant (optimistic) update. */
function toTaskPatch(changes: Partial<TaskInput>): Partial<Task> {
  const patch: Partial<Task> = {};
  if (changes.title !== undefined) patch.title = changes.title;
  if (changes.description !== undefined) patch.description = changes.description;
  if (changes.priority !== undefined) patch.priority = changes.priority;
  if (changes.due_date !== undefined) patch.due_date = changes.due_date;
  return patch;
}

/** The current values of the fields a change touches, so Undo can put them back. */
function revertFor(task: Task, changes: Partial<TaskInput>): Partial<TaskInput> {
  const revert: Partial<TaskInput> = {};
  if (changes.title !== undefined) revert.title = task.title;
  if (changes.description !== undefined) revert.description = task.description;
  if (changes.priority !== undefined) revert.priority = task.priority;
  if (changes.due_date !== undefined) revert.due_date = toDateOnly(task.due_date);
  if (changes.assigned_to !== undefined) revert.assigned_to = task.assignee?.id ?? null;
  return revert;
}

/** One-click and inline edits on the task page: shown at once, saved in the background, undoable. */
export default function useTaskQuickUpdate(): TaskQuickUpdate {
  const queryClient = useQueryClient();

  const mutation = useApiMutation({
    mutationFn: async ({ taskId, changes }: QuickUpdate) => {
      const key = queryKeys.tasks.detail(taskId);
      const previous = queryClient.getQueryData<Task>(key);
      if (previous) queryClient.setQueryData<Task>(key, { ...previous, ...toTaskPatch(changes) });
      try {
        return await tasksApi.update(taskId, changes);
      } catch (err) {
        if (previous) queryClient.setQueryData<Task>(key, previous);
        throw err;
      }
    },
    errorMessage: 'Failed to update task.',
    invalidate: [queryKeys.tasks.all, queryKeys.projects.all],
    onSuccess: (_, { taskId, message, revert }) => {
      if (!message) return;
      if (!revert) {
        showToast(message, 'info');
        return;
      }
      showUndoToast(message, () => mutation.mutate({ taskId, changes: revert, message: 'Change undone.' }));
    },
  });

  return {
    update: (task, changes, message) =>
      mutation.mutate({ taskId: task.id, changes, message, revert: message ? revertFor(task, changes) : undefined }),
    extend: (task, dueDate) =>
      mutation.mutate({
        taskId: task.id,
        changes: { due_date: dueDate },
        revert: { due_date: toDateOnly(task.due_date) },
        message: `"${task.title}" is now due ${formatDateOnly(dueDate)}.`,
      }),
    assignTo: (task, userId, name) =>
      mutation.mutate({
        taskId: task.id,
        changes: { assigned_to: userId },
        revert: { assigned_to: task.assignee?.id ?? null },
        message: userId === null ? `"${task.title}" is now unassigned.` : `"${task.title}" assigned to ${name ?? 'you'}.`,
      }),
    isPending: mutation.isPending,
  };
}
