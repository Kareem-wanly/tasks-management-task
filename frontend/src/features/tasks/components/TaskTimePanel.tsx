import { useState } from 'react';
import { ClockIcon, CloudOffIcon, PlusIcon } from 'lucide-react';
import tasksApi, { type TaskInput } from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import useApiMutation from '@/hooks/useApiMutation';
import { useAuth } from '@/lib/auth/AuthContext';
import { errorMessage } from '@/lib/apiClient';
import { cn } from '@/lib/utils';
import type { Task } from '@/types/api';
import useTaskTime, { spentLabel } from '../hooks/useTaskTime';
import TaskTimerButton from './TaskTimerButton';
import TimeEntryForm from './TimeEntryForm';
import TimeEntryRow from './TimeEntryRow';
import TimeEstimateEditor from './TimeEstimateEditor';

interface TaskTimePanelProps {
  task: Task;
}

/** Time tracking (Bitrix24 step 4.4): planned vs spent, the timer and the time log. */
export default function TaskTimePanel({ task }: TaskTimePanelProps) {
  const { can } = useAuth();
  const time = useTaskTime(task.id);
  const [logging, setLogging] = useState<boolean>(false);
  const { data: log, isPending, isError, error } = time.query;
  const canEditTask = can('tasks.update');

  const settings = useApiMutation({
    mutationFn: (changes: Partial<TaskInput>) => tasksApi.update(task.id, changes),
    successMessage: 'Time tracking updated.',
    errorMessage: 'Failed to update time tracking.',
    invalidate: [queryKeys.tasks.detail(task.id), queryKeys.tasks.time(task.id)],
  });

  if (isPending) {
    return (
      <Panel title="Time">
        <Skeleton className="h-16 w-full" />
      </Panel>
    );
  }
  if (isError || !log) {
    return (
      <Panel title="Time">
        <p className="text-[13px] text-danger">{errorMessage(error, "Couldn't load the time log.")}</p>
      </Panel>
    );
  }

  if (!log.allow_time_tracking) {
    if (!canEditTask) return null;
    return (
      <Panel title="Time">
        <p className="mb-3 text-[13px] text-muted-foreground">Time tracking is off for this task.</p>
        <Button
          type="button"
          variant="outline"
          disabled={settings.isPending}
          onClick={() => settings.mutate({ allow_time_tracking: true })}
        >
          <ClockIcon />
          Enable time tracking
        </Button>
      </Panel>
    );
  }

  const over = log.time_estimate > 0 && log.time_spent > log.time_estimate;
  const percent = log.time_estimate > 0 ? Math.min(100, (log.time_spent / log.time_estimate) * 100) : 0;

  return (
    <Panel title="Time" description={spentLabel(log.time_spent, log.time_estimate)} bodyClassName="space-y-3">
      {log.stale && (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <CloudOffIcon className="size-3.5" />
          Bitrix24 is unreachable; this may be out of date.
        </p>
      )}
      {log.time_estimate > 0 && (
        <div
          className="h-1.5 overflow-hidden rounded-full bg-muted"
          role="img"
          aria-label={`${Math.round(percent)}% of the planned time used`}
        >
          <div className={cn('h-full rounded-full bg-brand transition-all', over && 'bg-danger', progressWidth(percent))} />
        </div>
      )}
      <TimeEstimateEditor
        estimate={log.time_estimate}
        canEdit={canEditTask}
        pending={settings.isPending}
        onSave={(seconds) => settings.mutate({ time_estimate: seconds })}
      />

      {log.can_log && <TaskTimerButton taskId={task.id} log={log} time={time} />}

      {log.can_log &&
        (logging ? (
          <TimeEntryForm
            pending={time.isPending}
            onSubmit={(input, reset) =>
              time.add(input, () => {
                reset();
                setLogging(false);
              })
            }
            onCancel={() => setLogging(false)}
          />
        ) : (
          <Button type="button" variant="ghost" size="sm" onClick={() => setLogging(true)}>
            <PlusIcon />
            Log time manually
          </Button>
        ))}

      {log.entries.length > 0 && (
        <ul className="divide-y divide-border">
          {log.entries.map((entry) => (
            <TimeEntryRow key={entry.id} entry={entry} time={time} />
          ))}
        </ul>
      )}
    </Panel>
  );
}

/** Tailwind needs whole class names, so the bar width is picked from steps of 5%. */
function progressWidth(percent: number): string {
  const steps = [
    'w-0',
    'w-[5%]',
    'w-[10%]',
    'w-[15%]',
    'w-[20%]',
    'w-[25%]',
    'w-[30%]',
    'w-[35%]',
    'w-[40%]',
    'w-[45%]',
    'w-[50%]',
    'w-[55%]',
    'w-[60%]',
    'w-[65%]',
    'w-[70%]',
    'w-[75%]',
    'w-[80%]',
    'w-[85%]',
    'w-[90%]',
    'w-[95%]',
    'w-full',
  ];
  return steps[Math.round(percent / 5)] ?? 'w-full';
}
