import { useState } from 'react';
import { PencilIcon, Trash2Icon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import { Button } from '@/components/ui/button';
import type { TimeEntry } from '@/types/api';
import { formatDuration, formatTimestampDate } from '@/utils/format';
import type { TaskTimeControl } from '../hooks/useTaskTime';
import TimeEntryForm from './TimeEntryForm';

interface TimeEntryRowProps {
  entry: TimeEntry;
  time: TaskTimeControl;
}

/** One time log entry: who, how long, what for; its author edits or deletes it. */
export default function TimeEntryRow({ entry, time }: TimeEntryRowProps) {
  const [editing, setEditing] = useState<boolean>(false);

  if (editing) {
    return (
      <li className="py-2">
        <TimeEntryForm
          entry={entry}
          pending={time.isPending}
          onSubmit={(input) => time.update(entry, input, () => setEditing(false))}
          onCancel={() => setEditing(false)}
        />
      </li>
    );
  }

  return (
    <li className="group flex min-w-0 items-start gap-2 py-2 text-[13px]">
      {entry.user && <UserAvatar user={entry.user} size="xs" className="mt-0.5" />}
      <div className="min-w-0 flex-1">
        <div className="flex items-baseline gap-2">
          <span className="font-medium tabular-nums">{formatDuration(entry.seconds)}</span>
          <span className="truncate text-xs text-muted-foreground">
            {entry.user?.name ?? 'Unknown'} · {formatTimestampDate(entry.created_date)}
          </span>
        </div>
        {entry.comment && <p className="break-words text-muted-foreground">{entry.comment}</p>}
      </div>
      {entry.can_edit && (
        <div className="flex opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
          <Button type="button" variant="ghost" size="icon-xs" onClick={() => setEditing(true)} aria-label="Edit time entry">
            <PencilIcon />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            disabled={time.isPending}
            onClick={() => time.remove(entry)}
            aria-label="Delete time entry"
          >
            <Trash2Icon />
          </Button>
        </div>
      )}
    </li>
  );
}
