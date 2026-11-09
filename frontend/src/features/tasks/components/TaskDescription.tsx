import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { FileTextIcon, ListChecksIcon, PencilIcon } from 'lucide-react';
import { motion } from 'motion/react';
import EmptyState from '@/components/data/EmptyState';
import Markdown from '@/components/data/Markdown';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { EASE } from '@/lib/motion';
import { checklistProgress, toggleChecklistLine } from '@/utils/checklist';
import { descriptionSchema, type DescriptionFormValues } from '../schemas';

interface TaskDescriptionProps {
  description: string | null;
  canEdit: boolean;
  /** `notify` is false for checklist ticks, which save silently. */
  onSave: (description: string | null, notify: boolean) => void;
  saving: boolean;
}

/** Markdown description with a clickable checklist ("- [ ] step"), edited in place. */
export default function TaskDescription({ description, canEdit, onSave, saving }: TaskDescriptionProps) {
  const [editing, setEditing] = useState(false);
  const form = useForm<DescriptionFormValues>({
    resolver: zodResolver(descriptionSchema),
    values: { description: description ?? '' },
  });
  const progress = checklistProgress(description);

  const cancel = () => {
    form.reset({ description: description ?? '' });
    setEditing(false);
  };
  const submit = form.handleSubmit((values) => {
    setEditing(false);
    const next = values.description.trim() || null;
    if (next !== description) onSave(next, true);
  });

  return (
    <Panel
      title="Description"
      actions={
        <>
          {progress && !editing && (
            <span className="flex items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
              <ListChecksIcon className="size-3.5" />
              {progress.done}/{progress.total}
            </span>
          )}
          {canEdit && !editing && description && (
            <Button size="sm" variant="ghost" onClick={() => setEditing(true)}>
              <PencilIcon />
              Edit
            </Button>
          )}
        </>
      }
    >
      {editing ? (
        <form onSubmit={submit} noValidate className="space-y-3">
          <Textarea
            {...form.register('description')}
            autoFocus
            rows={8}
            aria-label="Task description"
            placeholder={'Context, links and steps…\n\n- [ ] First step\n- [ ] Second step'}
            className="field-sizing-fixed min-h-40 resize-y font-mono text-[13px]"
            onKeyDown={(e) => {
              if (e.key === 'Escape') {
                e.preventDefault();
                cancel();
              } else if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                void submit();
              }
            }}
          />
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-xs text-muted-foreground">
              Markdown supported. Start a line with <code className="font-mono">- [ ]</code> for a checklist item.
            </p>
            <div className="ms-auto flex gap-2">
              <Button type="button" variant="ghost" size="sm" onClick={cancel}>
                Cancel
              </Button>
              <Button type="submit" variant="brand" size="sm">
                Save
              </Button>
            </div>
          </div>
        </form>
      ) : description ? (
        <>
          {progress && (
            <div className="mb-4 h-1 overflow-hidden rounded-full bg-muted" aria-hidden="true">
              <motion.div
                className="h-full rounded-full bg-success"
                initial={false}
                animate={{ width: `${Math.round((progress.done / progress.total) * 100)}%` }}
                transition={{ duration: 0.3, ease: EASE }}
              />
            </div>
          )}
          <Markdown
            busy={saving}
            onToggleItem={canEdit ? (line) => onSave(toggleChecklistLine(description, line), false) : undefined}
          >
            {description}
          </Markdown>
        </>
      ) : (
        <EmptyState
          icon={FileTextIcon}
          title="No description"
          description={canEdit ? 'Add context, links or a checklist for this task.' : 'No details were added to this task.'}
          action={
            canEdit && (
              <Button size="sm" variant="outline" onClick={() => setEditing(true)}>
                <PencilIcon />
                Add description
              </Button>
            )
          }
          className="py-8"
        />
      )}
    </Panel>
  );
}
