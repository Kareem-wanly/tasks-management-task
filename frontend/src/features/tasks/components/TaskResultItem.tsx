import { useState } from 'react';
import { PencilIcon, Trash2Icon } from 'lucide-react';
import UserAvatar from '@/components/data/UserAvatar';
import { Button } from '@/components/ui/button';
import type { TaskResult } from '@/types/api';
import { bbcodeToText, formatDateTime } from '@/utils/format';
import type { TaskResultsControl } from '../hooks/useTaskResults';
import TaskResultForm from './TaskResultForm';

interface TaskResultItemProps {
  result: TaskResult;
  results: TaskResultsControl;
}

/** One result: who recorded it, when, and the text; its author edits or deletes it. */
export default function TaskResultItem({ result, results }: TaskResultItemProps) {
  const [editing, setEditing] = useState<boolean>(false);

  return (
    <li className="group flex gap-3 py-3 text-[13px]">
      {result.author && <UserAvatar user={result.author} size="sm" className="mt-0.5" />}
      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex items-center gap-2">
          <span className="font-medium">{result.author?.name ?? 'Unknown'}</span>
          <span className="text-xs text-muted-foreground">{formatDateTime(result.created_at)}</span>
          {result.can_edit && !editing && (
            <span className="ms-auto flex opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
              <Button type="button" variant="ghost" size="icon-xs" onClick={() => setEditing(true)} aria-label="Edit result">
                <PencilIcon />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                disabled={results.isPending}
                onClick={() => results.remove(result)}
                aria-label="Delete result"
              >
                <Trash2Icon />
              </Button>
            </span>
          )}
        </div>
        {editing ? (
          <TaskResultForm
            // The raw text: Bitrix formatting survives the edit.
            initial={result.text}
            pending={results.isPending}
            submitLabel="Save"
            onSubmit={(text) => results.update(result, text, () => setEditing(false))}
            onCancel={() => setEditing(false)}
          />
        ) : (
          <p className="whitespace-pre-wrap break-words">{bbcodeToText(result.text)}</p>
        )}
      </div>
    </li>
  );
}
