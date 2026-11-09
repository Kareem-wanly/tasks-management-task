import { useQueryClient } from '@tanstack/react-query';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import { TASK_STATUS_LABELS } from '@/constants/statuses';
import useApiMutation from '@/hooks/useApiMutation';
import { showToast, showUndoToast } from '@/lib/toast/ToastContext';
import type { Task, TaskStatus } from '@/types/api';

export type StatusTask = Pick<Task, 'id' | 'title' | 'status'>;

interface StatusChange {
  task: StatusTask;
  to: TaskStatus;
  /** True for the change made by Undo, which gets no Undo button of its own. */
  isUndo?: boolean;
}

export interface TaskStatusControl {
  change: (task: StatusTask, to: TaskStatus) => void;
  isPending: boolean;
}

/**
 * Changes a task's status without a confirmation step: the open task page updates at once,
 * and the toast offers Undo instead.
 */
export default function useTaskStatus(): TaskStatusControl {
  const queryClient = useQueryClient();

  const mutation = useApiMutation({
    mutationFn: async ({ task, to }: StatusChange) => {
      const key = queryKeys.tasks.detail(task.id);
      const previous = queryClient.getQueryData<Task>(key);
      if (previous) {
        queryClient.setQueryData<Task>(key, {
          ...previous,
          status: to,
          completed_at: to === 'completed' ? new Date().toISOString() : null,
        });
      }
      try {
        return await tasksApi.updateStatus(task.id, to);
      } catch (err) {
        if (previous) queryClient.setQueryData<Task>(key, previous);
        throw err;
      }
    },
    errorMessage: 'Failed to update task status.',
    // Lists may hold an optimistic move (the board); reload them so a failed change snaps back
    onError: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tasks.lists });
    },
    // Project pages show task counts, so they refresh too
    invalidate: [queryKeys.tasks.all, queryKeys.projects.all],
    onSuccess: (_, { task, to, isUndo }) => {
      if (isUndo) {
        showToast('Change undone.', 'info');
        return;
      }
      const message =
        to === 'completed' ? `"${task.title}" marked as completed.` : `"${task.title}" moved to ${TASK_STATUS_LABELS[to]}.`;
      showUndoToast(message, () =>
        mutation.mutate({ task: { ...task, status: to }, to: task.status, isUndo: true })
      );
    },
  });

  return {
    change: (task, to) => {
      if (task.status !== to) mutation.mutate({ task, to });
    },
    isPending: mutation.isPending,
  };
}
