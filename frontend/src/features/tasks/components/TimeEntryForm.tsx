import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { TimeEntryInput } from '@/api/timeApi';
import type { TimeEntry } from '@/types/api';
import { timeEntrySchema, type TimeEntryFormValues } from '../schemas';

interface TimeEntryFormProps {
  /** Editing this entry; omit to log new time. */
  entry?: TimeEntry;
  pending: boolean;
  onSubmit: (input: TimeEntryInput, reset: () => void) => void;
  onCancel?: () => void;
}

const toValues = (entry?: TimeEntry): TimeEntryFormValues => ({
  hours: entry ? String(Math.floor(entry.seconds / 3600)) : '',
  minutes: entry ? String(Math.floor((entry.seconds % 3600) / 60)) : '',
  comment: entry?.comment ?? '',
});

/** Hours, minutes and a comment for one time entry. */
export default function TimeEntryForm({ entry, pending, onSubmit, onCancel }: TimeEntryFormProps) {
  const form = useForm<TimeEntryFormValues>({
    resolver: zodResolver(timeEntrySchema),
    defaultValues: toValues(entry),
  });
  const { errors } = form.formState;

  const submit = form.handleSubmit((values) =>
    onSubmit(
      {
        hours: Number(values.hours || 0),
        minutes: Number(values.minutes || 0),
        comment: values.comment.trim() || null,
      },
      () => form.reset(toValues())
    )
  );

  return (
    <form onSubmit={submit} noValidate className="space-y-2">
      <div className="flex items-start gap-2">
        <Field data-invalid={Boolean(errors.hours)} className="w-20">
          <Input
            {...form.register('hours')}
            inputMode="numeric"
            placeholder="0"
            aria-label="Hours"
            aria-invalid={Boolean(errors.hours)}
          />
        </Field>
        <span className="pt-2 text-xs text-muted-foreground">h</span>
        <Field data-invalid={Boolean(errors.minutes)} className="w-20">
          <Input
            {...form.register('minutes')}
            inputMode="numeric"
            placeholder="0"
            aria-label="Minutes"
            aria-invalid={Boolean(errors.minutes)}
          />
        </Field>
        <span className="pt-2 text-xs text-muted-foreground">m</span>
      </div>
      <FieldError errors={[errors.hours, errors.minutes]} />
      <Field data-invalid={Boolean(errors.comment)}>
        <Input {...form.register('comment')} placeholder="What did you work on? (optional)" aria-label="Comment" />
        <FieldError errors={[errors.comment]} />
      </Field>
      <div className="flex justify-end gap-2">
        {onCancel && (
          <Button type="button" variant="ghost" size="sm" onClick={onCancel}>
            Cancel
          </Button>
        )}
        <Button type="submit" size="sm" variant="outline" disabled={pending}>
          {entry ? 'Save' : 'Log time'}
        </Button>
      </div>
    </form>
  );
}
