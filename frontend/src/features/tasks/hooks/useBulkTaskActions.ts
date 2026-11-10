import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import useTaskLookups, { type TaskLookups } from '@/hooks/queries/useTaskLookups';
import useApiMutation from '@/hooks/useApiMutation';
import { errorMessage } from '@/lib/apiClient';
import { showToast } from '@/lib/toast/ToastContext';
import type { Task, TaskPriority, TaskStatus } from '@/types/api';
import { addDays, extensionBase } from '@/utils/dates';

export type BulkAction =
  | { kind: 'status'; status: TaskStatus }
  | { kind: 'priority'; priority: TaskPriority }
  | { kind: 'extend'; days: number }
  | { kind: 'assign'; userId: number | null }
  | { kind: 'delete' };

interface BulkResult {
  done: number;
  failed: number;
  /** The first error, to explain why some tasks were skipped. */
  firstError: unknown;
}

interface BulkRequest {
  tasks: Task[];
  action: BulkAction;
}

/** The API has no batch endpoint, so each task gets its own request; one failure doesn't stop the rest. */
function request(task: Task, action: BulkAction): Promise<unknown> {
  switch (action.kind) {
    case 'status':
      return tasksApi.updateStatus(task.id, action.status);
    case 'priority':
      return tasksApi.update(task.id, { priority: action.priority });
    case 'extend':
      return tasksApi.update(task.id, { due_date: addDays(extensionBase(task.due_date), action.days) });
    case 'assign':
      return tasksApi.update(task.id, { assigned_to: action.userId });
    case 'delete':
      return tasksApi.delete(task.id);
  }
}

function describe(action: BulkAction, count: number, lookups: TaskLookups): string {
  const tasks = `${count} task${count === 1 ? '' : 's'}`;
  switch (action.kind) {
    case 'status':
      return `Moved ${tasks} to ${lookups.statusLabel(action.status)}.`;
    case 'priority':
      return `Set ${tasks} to ${lookups.priorityLabel(action.priority)} priority.`;
    case 'extend':
      return `Pushed back ${tasks} by ${action.days} days.`;
    case 'assign':
      return action.userId === null ? `Unassigned ${tasks}.` : `Assigned ${tasks} to you.`;
    case 'delete':
      return `Deleted ${tasks}.`;
  }
}

/** Runs one action on many tasks and reports how many succeeded. */
export default function useBulkTaskActions(onDone: () => void) {
  const lookups = useTaskLookups();

  return useApiMutation({
    mutationFn: async ({ tasks, action }: BulkRequest): Promise<BulkResult> => {
      const results = await Promise.allSettled(tasks.map((task) => request(task, action)));
      const rejected = results.filter((r): r is PromiseRejectedResult => r.status === 'rejected');
      return { done: results.length - rejected.length, failed: rejected.length, firstError: rejected[0]?.reason };
    },
    errorMessage: 'The bulk action failed.',
    invalidate: [queryKeys.tasks.all, queryKeys.projects.all],
    onSuccess: ({ done, failed, firstError }, { action }) => {
      if (done > 0) showToast(describe(action, done, lookups), 'success');
      if (failed > 0) {
        showToast(`${failed} task${failed === 1 ? '' : 's'} skipped: ${errorMessage(firstError, 'not allowed.')}`, 'error', 6000);
      }
      onDone();
    },
  });
}
