import { useState } from 'react';
import { ChevronDownIcon, ChevronUpIcon, CloudOffIcon, HistoryIcon } from 'lucide-react';
import EmptyState from '@/components/data/EmptyState';
import UserAvatar from '@/components/data/UserAvatar';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import { errorMessage } from '@/lib/apiClient';
import type { TaskHistoryUnavailable } from '@/types/api';
import { formatDateTime } from '@/utils/format';
import useTaskHistory from '../hooks/useTaskHistory';
import { describeHistoryEntry } from '../historyText';

interface TaskHistoryPanelProps {
  taskId: number;
}

const UNAVAILABLE: Record<TaskHistoryUnavailable, string> = {
  not_in_bitrix: 'This task is not in Bitrix24, so it has no history there.',
  unreachable: 'Bitrix24 is not responding. Try again in a moment.',
  unavailable: 'Bitrix24 does not show this task’s history (it may have been deleted there).',
};

/** The task's change history as Bitrix24 records it (step 4.8). Closed until asked for: each read asks Bitrix24. */
export default function TaskHistoryPanel({ taskId }: TaskHistoryPanelProps) {
  const [open, setOpen] = useState<boolean>(false);
  const history = useTaskHistory(taskId, open);
  const labels = useTaskLookups();
  const { data, isPending, isError, error } = history;

  return (
    <Panel
      title="History"
      description={open && data && data.entries.length > 0 ? `${data.entries.length} changes` : undefined}
      actions={
        <Button type="button" variant="ghost" size="sm" onClick={() => setOpen((value) => !value)} aria-expanded={open}>
          {open ? <ChevronUpIcon /> : <ChevronDownIcon />}
          {open ? 'Hide' : 'Show history'}
        </Button>
      }
    >
      {!open ? (
        <p className="text-[13px] text-muted-foreground">Who changed what, and when, as recorded in Bitrix24.</p>
      ) : (
        <>
          {isPending && <Skeleton className="h-24 w-full" />}
          {isError && <p className="text-[13px] text-danger">{errorMessage(error, "Couldn't load the history.")}</p>}
          {data?.unavailableBecause && (
            <p className="flex items-center gap-1.5 text-[13px] text-muted-foreground">
              <CloudOffIcon className="size-3.5" />
              {UNAVAILABLE[data.unavailableBecause]}
            </p>
          )}
          {data && !data.unavailableBecause && data.entries.length === 0 && (
            <EmptyState icon={HistoryIcon} title="No changes yet" className="py-6" />
          )}
          {data && data.entries.length > 0 && (
            <ol className="max-h-[28rem] divide-y divide-border overflow-y-auto pe-1">
              {data.entries.map((entry) => (
                <li key={entry.id} className="flex gap-3 py-2.5 text-[13px]">
                  <UserAvatar user={{ id: entry.user?.id ?? null, name: entry.user?.name ?? 'Someone' }} size="sm" className="mt-0.5" />
                  <div className="min-w-0 flex-1">
                    <p className="break-words">
                      <span className="font-medium">{entry.user?.name ?? 'Someone'}</span> {describeHistoryEntry(entry, labels)}
                    </p>
                    <p className="text-xs text-muted-foreground tabular-nums">{formatDateTime(entry.created_at)}</p>
                  </div>
                </li>
              ))}
            </ol>
          )}
        </>
      )}
    </Panel>
  );
}
