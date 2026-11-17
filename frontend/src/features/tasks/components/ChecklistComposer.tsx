import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { PlusIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { ChecklistControl } from '../hooks/useTaskChecklist';
import { checklistItemSchema, type ChecklistItemFormValues } from '../schemas';

interface ChecklistComposerProps {
  checklist: ChecklistControl;
}

/** Adds an item to the first checklist (Bitrix24 creates it when there is none). */
export default function ChecklistComposer({ checklist }: ChecklistComposerProps) {
  const form = useForm<ChecklistItemFormValues>({ resolver: zodResolver(checklistItemSchema), defaultValues: { title: '' } });
  const error = form.formState.errors.title;

  return (
    <form
      noValidate
      className="flex items-start gap-2"
      onSubmit={form.handleSubmit((values) => checklist.add(values.title, undefined, () => form.reset({ title: '' })))}
    >
      <Field data-invalid={Boolean(error)} className="flex-1">
        <Input
          {...form.register('title')}
          aria-label="New checklist item"
          aria-invalid={Boolean(error)}
          placeholder="Add an item…"
          disabled={checklist.isPending}
        />
        <FieldError errors={[error]} />
      </Field>
      <Button type="submit" variant="outline" disabled={checklist.isPending}>
        <PlusIcon />
        Add
      </Button>
    </form>
  );
}
