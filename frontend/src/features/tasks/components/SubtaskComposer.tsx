import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Loader2Icon, PlusIcon } from 'lucide-react';
import tasksApi from '@/api/tasksApi';
import { queryKeys } from '@/api/queryKeys';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import useApiMutation from '@/hooks/useApiMutation';
import { applyServerErrors } from '@/lib/forms';
import type { Task } from '@/types/api';
import { subtaskSchema, type SubtaskFormValues } from '../schemas';

interface SubtaskComposerProps {
  parent: Task;
}

/** One-line "add a subtask": created in the parent's project, under the parent (Bitrix24 PARENT_ID). */
export default function SubtaskComposer({ parent }: SubtaskComposerProps) {
  const form = useForm<SubtaskFormValues>({ resolver: zodResolver(subtaskSchema), defaultValues: { title: '' } });

  const create = useApiMutation({
    mutationFn: (values: SubtaskFormValues) =>
      tasksApi.create(parent.project?.id ?? null, { title: values.title, parent_id: parent.id }),
    successMessage: 'Subtask added.',
    errorMessage: 'Failed to add the subtask.',
    invalidate: [queryKeys.tasks.detail(parent.id), queryKeys.tasks.lists, queryKeys.projects.all],
    onSuccess: () => form.reset({ title: '' }),
    onError: (err) => applyServerErrors(err, form.setError),
  });

  const error = form.formState.errors.title;

  return (
    <form onSubmit={form.handleSubmit((values) => create.mutate(values))} noValidate className="flex items-start gap-2">
      <Field data-invalid={Boolean(error)} className="flex-1">
        <Input
          {...form.register('title')}
          aria-label="New subtask title"
          aria-invalid={Boolean(error)}
          placeholder="Add a subtask…"
          disabled={create.isPending}
        />
        <FieldError errors={[error]} />
      </Field>
      <Button type="submit" variant="outline" disabled={create.isPending}>
        {create.isPending ? <Loader2Icon className="animate-spin" /> : <PlusIcon />}
        Add
      </Button>
    </form>
  );
}
