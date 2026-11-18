import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { taskResultSchema, type TaskResultFormValues } from '../schemas';

interface TaskResultFormProps {
  /** The current text when editing; empty for a new result. */
  initial?: string;
  pending: boolean;
  submitLabel: string;
  onSubmit: (text: string, reset: () => void) => void;
  onCancel?: () => void;
}

/** The text of a result; Ctrl/⌘ + Enter saves. */
export default function TaskResultForm({ initial = '', pending, submitLabel, onSubmit, onCancel }: TaskResultFormProps) {
  const form = useForm<TaskResultFormValues>({ resolver: zodResolver(taskResultSchema), defaultValues: { text: initial } });
  const error = form.formState.errors.text;
  const submit = form.handleSubmit((values) => onSubmit(values.text, () => form.reset({ text: '' })));

  return (
    <form onSubmit={submit} noValidate className="space-y-2">
      <Field data-invalid={Boolean(error)}>
        <Textarea
          {...form.register('text')}
          rows={3}
          aria-label="Result"
          aria-invalid={Boolean(error)}
          placeholder="What was achieved? (shown as the task's result in Bitrix24)"
          disabled={pending}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
              e.preventDefault();
              void submit();
            }
          }}
        />
        <FieldError errors={[error]} />
      </Field>
      <div className="flex justify-end gap-2">
        {onCancel && (
          <Button type="button" variant="ghost" size="sm" onClick={onCancel}>
            Cancel
          </Button>
        )}
        <Button type="submit" size="sm" variant="outline" disabled={pending}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
