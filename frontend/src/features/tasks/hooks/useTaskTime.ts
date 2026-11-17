import { useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import timeApi, { type TimeEntryInput } from '@/api/timeApi';
import useApiMutation from '@/hooks/useApiMutation';
import { showToast } from '@/lib/toast/ToastContext';
import type { TimeEntry, TimeLog } from '@/types/api';
import { formatDuration } from '@/utils/format';

type TimeChange =
  | { kind: 'add'; entry: TimeEntryInput }
  | { kind: 'update'; target: TimeEntry; entry: TimeEntryInput }
  | { kind: 'remove'; target: TimeEntry }
  | { kind: 'start' }
  | { kind: 'stop'; comment: string | null };

export interface TaskTimeControl {
  query: UseQueryResult<TimeLog>;
  add: (entry: TimeEntryInput, onDone?: () => void) => void;
  update: (target: TimeEntry, entry: TimeEntryInput, onDone?: () => void) => void;
  remove: (target: TimeEntry) => void;
  start: () => void;
  stop: (comment: string | null) => void;
  isPending: boolean;
}

const MESSAGES: Record<TimeChange['kind'], string> = {
  add: 'Time logged.',
  update: 'Time entry updated.',
  remove: 'Time entry deleted.',
  start: 'Timer started.',
  stop: 'Timer stopped; the time is logged.',
};

/** A task's time log and the caller's timer (Bitrix24 step 4.4), read when the task opens. */
export default function useTaskTime(taskId: number): TaskTimeControl {
  const queryClient = useQueryClient();
  const key = queryKeys.tasks.time(taskId);

  const query = useQuery({
    queryKey: key,
    queryFn: ({ signal }) => timeApi.get(taskId, { signal }),
  });

  const mutation = useApiMutation({
    mutationFn: (change: TimeChange & { onDone?: () => void }) => {
      switch (change.kind) {
        case 'add':
          return timeApi.add(taskId, change.entry);
        case 'update':
          return timeApi.update(change.target.id, change.entry);
        case 'remove':
          return timeApi.remove(change.target.id);
        case 'start':
          return timeApi.startTimer(taskId);
        case 'stop':
          return timeApi.stopTimer(taskId, change.comment);
      }
    },
    successMessage: (_: TimeLog, change) => MESSAGES[change.kind],
    errorMessage: 'Failed to update the time log.',
    // Another task's log changes when its timer is stopped by this one.
    invalidate: [queryKeys.tasks.lists],
    onSuccess: (log, change) => {
      queryClient.setQueryData<TimeLog>(key, log);
      change.onDone?.();
      if (log.stopped_task) {
        showToast(`The timer on "${log.stopped_task.title}" was stopped and its time logged.`, 'info');
        void queryClient.invalidateQueries({ queryKey: queryKeys.tasks.time(log.stopped_task.id) });
      }
    },
  });

  return {
    query,
    add: (entry, onDone) => mutation.mutate({ kind: 'add', entry, onDone }),
    update: (target, entry, onDone) => mutation.mutate({ kind: 'update', target, entry, onDone }),
    remove: (target) => mutation.mutate({ kind: 'remove', target }),
    start: () => mutation.mutate({ kind: 'start' }),
    stop: (comment) => mutation.mutate({ kind: 'stop', comment }),
    isPending: mutation.isPending,
  };
}

/** "1h 30m of 4h", or just the time spent when nothing is planned. */
export function spentLabel(spent: number, estimate: number): string {
  return estimate > 0 ? `${formatDuration(spent)} of ${formatDuration(estimate)}` : formatDuration(spent);
}
