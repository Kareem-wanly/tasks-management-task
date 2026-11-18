import { useState } from 'react';
import { GripVerticalIcon, Trash2Icon } from 'lucide-react';
import SortableItem from '@/components/data/SortableItem';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { ChecklistItem } from '@/types/api';
import type { ChecklistControl } from '../hooks/useTaskChecklist';

interface ChecklistItemRowProps {
  item: ChecklistItem;
  canEdit: boolean;
  checklist: ChecklistControl;
}

/** One checklist item: drag handle, tick box, title (click to edit) and delete. */
export default function ChecklistItemRow({ item, canEdit, checklist }: ChecklistItemRowProps) {
  const [draft, setDraft] = useState<string | null>(null);

  const save = () => {
    const title = draft?.trim() ?? '';
    setDraft(null);
    if (title !== '' && title !== item.title) checklist.rename(item, title);
  };

  return (
    <SortableItem
      id={item.id}
      disabled={!canEdit || draft !== null}
      className="group flex min-w-0 items-center gap-2 py-1 text-[13px]"
    >
      {(handle) => (
        <>
          {canEdit && (
            <button
              type="button"
              {...handle.attributes}
              {...handle.listeners}
              aria-label={`Move "${item.title}"`}
              className="cursor-grab touch-none text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
            >
              <GripVerticalIcon className="size-4" />
            </button>
          )}
          <Checkbox
            checked={item.is_complete}
            disabled={!canEdit}
            onCheckedChange={(checked) => checklist.toggle(item, checked === true)}
            aria-label={`Mark "${item.title}" as ${item.is_complete ? 'not done' : 'done'}`}
          />
          {draft !== null ? (
            <Input
              autoFocus
              value={draft}
              aria-label="Item title"
              className="h-7 flex-1"
              onChange={(e: React.ChangeEvent<HTMLInputElement>) => setDraft(e.target.value)}
              onBlur={save}
              onKeyDown={(e) => {
                if (e.key === 'Enter') save();
                if (e.key === 'Escape') setDraft(null);
              }}
            />
          ) : (
            <button
              type="button"
              disabled={!canEdit}
              onClick={() => setDraft(item.title)}
              title={canEdit ? 'Click to edit' : undefined}
              className={cn(
                'min-w-0 flex-1 truncate text-start enabled:cursor-text',
                item.is_complete && 'text-muted-foreground line-through'
              )}
            >
              {item.title}
            </button>
          )}
          {canEdit && draft === null && (
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              onClick={() => checklist.remove(item)}
              aria-label={`Delete "${item.title}"`}
              className="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
            >
              <Trash2Icon />
            </Button>
          )}
        </>
      )}
    </SortableItem>
  );
}
