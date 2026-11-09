import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { PencilIcon } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { titleSchema, type TitleFormValues } from '../schemas';

interface InlineTitleProps {
  title: string;
  canEdit: boolean;
  onSave: (title: string) => void;
}

/** The task title; click (or press Enter on it) to rename in place. Enter or leaving saves, Esc cancels. */
export default function InlineTitle({ title, canEdit, onSave }: InlineTitleProps) {
  const [editing, setEditing] = useState(false);
  const form = useForm<TitleFormValues>({ resolver: zodResolver(titleSchema), values: { title } });

  if (!canEdit) return <>{title}</>;

  const submit = form.handleSubmit(({ title: next }) => {
    setEditing(false);
    if (next !== title) onSave(next);
  });

  if (!editing) {
    return (
      <button
        type="button"
        onClick={() => setEditing(true)}
        title="Rename task"
        className="group/title -mx-1 inline-flex max-w-full items-center gap-2 rounded-md px-1 text-start hover:bg-muted"
      >
        <span className="min-w-0 break-words">{title}</span>
        <PencilIcon className="size-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover/title:opacity-100" />
      </button>
    );
  }

  const error = form.formState.errors.title?.message;
  return (
    <form onSubmit={submit} noValidate className="max-w-2xl">
      <Input
        {...form.register('title')}
        autoFocus
        aria-label="Task title"
        aria-invalid={Boolean(error)}
        onBlur={() => void submit()}
        onKeyDown={(e) => {
          if (e.key === 'Escape') {
            e.preventDefault();
            form.reset({ title });
            setEditing(false);
          }
        }}
        className="h-auto py-1 text-2xl font-semibold tracking-tight md:text-[1.75rem]"
      />
      {error && <p className="mt-1 text-xs font-normal text-destructive">{error}</p>}
    </form>
  );
}
