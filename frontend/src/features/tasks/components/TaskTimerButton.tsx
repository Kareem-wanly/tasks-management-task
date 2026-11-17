import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { PlayIcon, SquareIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { TimeLog } from '@/types/api';
import { formatClock } from '@/utils/format';
import type { TaskTimeControl } from '../hooks/useTaskTime';

interface TaskTimerButtonProps {
  taskId: number;
  log: TimeLog;
  time: TaskTimeControl;
}

/** Seconds since the timer started, ticking once a second while it runs. */
function useElapsed(startedAt: string | null): number {
  const [now, setNow] = useState<number>(() => Date.now());

  useEffect(() => {
    if (!startedAt) return;
    const id = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(id);
  }, [startedAt]);

  return startedAt ? Math.max(0, (now - new Date(startedAt).getTime()) / 1000) : 0;
}

/** Start/stop the caller's timer; stopping logs the time to Bitrix24. */
export default function TaskTimerButton({ taskId, log, time }: TaskTimerButtonProps) {
  const runningHere = log.timer?.task_id === taskId;
  const elapsed = useElapsed(runningHere ? (log.timer?.started_at ?? null) : null);
  const elsewhere = log.timer && !runningHere ? log.timer : null;

  return (
    <div className="space-y-1.5">
      {runningHere ? (
        <Button
          type="button"
          variant="outline"
          className="w-full justify-between"
          disabled={time.isPending}
          onClick={() => time.stop(null)}
        >
          <span className="flex items-center gap-2 text-danger">
            <SquareIcon className="fill-current" />
            Stop timer
          </span>
          <span className="font-mono tabular-nums">{formatClock(elapsed)}</span>
        </Button>
      ) : (
        <Button type="button" variant="outline" className="w-full" disabled={time.isPending || !log.can_log} onClick={time.start}>
          <PlayIcon />
          Start timer
        </Button>
      )}
      {elsewhere && (
        <p className="text-xs text-muted-foreground">
          Your timer is running on{' '}
          <Link to={`/tasks/${elsewhere.task_id}`} className="font-medium hover:text-brand">
            {elsewhere.task_title ?? 'another task'}
          </Link>
          ; starting here stops it and logs its time.
        </p>
      )}
    </div>
  );
}
