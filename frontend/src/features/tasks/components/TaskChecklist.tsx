import { CloudOffIcon } from 'lucide-react';
import Panel from '@/components/layout/Panel';
import { Skeleton } from '@/components/ui/skeleton';
import { errorMessage } from '@/lib/apiClient';
import type { ChecklistItem } from '@/types/api';
import useTaskChecklist from '../hooks/useTaskChecklist';
import ChecklistComposer from './ChecklistComposer';
import ChecklistGroup from './ChecklistGroup';

interface TaskChecklistProps {
  taskId: number;
}

const bySort = (a: ChecklistItem, b: ChecklistItem) => a.sort_index - b.sort_index || a.id - b.id;

/** The task's checklists (Bitrix24 step 4.3), fresh from Bitrix24 each time the task opens. */
export default function TaskChecklist({ taskId }: TaskChecklistProps) {
  const checklist = useTaskChecklist(taskId);
  const { data, isPending, isError, error } = checklist.query;

  const lists = (data?.items ?? []).filter((i) => i.parent_id === null).sort(bySort);
  const itemsOf = (listId: number) => (data?.items ?? []).filter((i) => i.parent_id === listId).sort(bySort);
  const items = lists.flatMap((list) => itemsOf(list.id));
  const done = items.filter((i) => i.is_complete).length;
  const canEdit = data?.can_edit ?? false;

  if (data && items.length === 0 && !canEdit) return null;

  return (
    <Panel
      title="Checklist"
      description={items.length > 0 ? `${done} of ${items.length} done` : undefined}
      bodyClassName="space-y-3"
    >
      {isPending && <Skeleton className="h-16 w-full" />}
      {isError && <p className="text-[13px] text-danger">{errorMessage(error, "Couldn't load the checklist.")}</p>}
      {data?.stale && (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <CloudOffIcon className="size-3.5" />
          Bitrix24 is unreachable; this may be out of date.
        </p>
      )}
      {lists.map((list) => (
        <ChecklistGroup
          key={list.id}
          title={lists.length > 1 ? list.title : undefined}
          items={itemsOf(list.id)}
          canEdit={canEdit}
          checklist={checklist}
        />
      ))}
      {canEdit && <ChecklistComposer checklist={checklist} />}
    </Panel>
  );
}
