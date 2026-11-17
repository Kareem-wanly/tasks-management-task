import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { PencilIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { formatDuration } from '@/utils/format';

const estimateSchema = z.object({
  hours: z.string().regex(/^\d{0,4}([.,]\d{1,2})?$/, 'Hours, e.g. 4 or 1.5.'),
});
type EstimateValues = z.infer<typeof estimateSchema>;

interface TimeEstimateEditorProps {
  /** Seconds; 0 = not planned. */
  estimate: number;
  canEdit: boolean;
  pending: boolean;
  onSave: (seconds: number) => void;
}

/** The planned time (Bitrix24 TIME_ESTIMATE), edited in hours. */
export default function TimeEstimateEditor({ estimate, canEdit, pending, onSave }: TimeEstimateEditorProps) {
  const [editing, setEditing] = useState<boolean>(false);
  const form = useForm<EstimateValues>({ resolver: zodResolver(estimateSchema), defaultValues: { hours: '' } });

  if (!editing) {
    return (
      <div className="flex items-center gap-1 text-xs text-muted-foreground">
        Planned: {estimate > 0 ? formatDuration(estimate) : 'not set'}
        {canEdit && (
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label="Edit planned time"
            onClick={() => {
              form.reset({ hours: estimate > 0 ? String(Math.round((estimate / 3600) * 100) / 100) : '' });
              setEditing(true);
            }}
          >
            <PencilIcon />
          </Button>
        )}
      </div>
    );
  }

  return (
    <form
      noValidate
      className="flex items-start gap-2"
      onSubmit={form.handleSubmit((values) => {
        onSave(Math.round(Number(values.hours.replace(',', '.') || 0) * 3600));
        setEditing(false);
      })}
    >
      <Field data-invalid={Boolean(form.formState.errors.hours)} className="w-24">
        <Input {...form.register('hours')} autoFocus inputMode="decimal" placeholder="Hours" aria-label="Planned hours" />
        <FieldError errors={[form.formState.errors.hours]} />
      </Field>
      <Button type="submit" size="sm" variant="outline" disabled={pending}>
        Save
      </Button>
      <Button type="button" size="sm" variant="ghost" onClick={() => setEditing(false)}>
        Cancel
      </Button>
    </form>
  );
}
