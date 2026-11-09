import { Controller, type Control } from 'react-hook-form';
import { TriangleAlertIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { daysFromNow, toDateOnly } from '@/utils/dates';
import { formatDateOnly } from '@/utils/format';
import type { TaskFormValues } from '../schemas';

interface DueDateFieldProps {
  control: Control<TaskFormValues>;
  /** The project's deadline: offered as a preset and used for the "after the project" warning. */
  projectDueDate?: string | null;
  disabled?: boolean;
}

/** Date input with one-click presets. */
export default function DueDateField({ control, projectDueDate, disabled }: DueDateFieldProps) {
  const projectDue = toDateOnly(projectDueDate);
  const presets = [
    { label: 'Today', value: daysFromNow(0) },
    { label: 'Tomorrow', value: daysFromNow(1) },
    { label: 'In a week', value: daysFromNow(7) },
    ...(projectDue ? [{ label: 'Project deadline', value: projectDue }] : []),
  ];

  return (
    <Controller
      control={control}
      name="due_date"
      render={({ field, fieldState }) => (
        <Field data-invalid={fieldState.invalid}>
          <FieldLabel htmlFor="due_date">Due date</FieldLabel>
          <Input
            {...field}
            id="due_date"
            type="date"
            disabled={disabled}
            aria-invalid={fieldState.invalid}
          />
          <div className="flex flex-wrap gap-1.5">
            {presets.map((preset) => (
              <Button
                key={preset.label}
                type="button"
                size="xs"
                variant={field.value === preset.value ? 'secondary' : 'ghost'}
                disabled={disabled}
                onClick={() => field.onChange(preset.value)}
              >
                {preset.label}
              </Button>
            ))}
            {field.value && (
              <Button type="button" size="xs" variant="ghost" disabled={disabled} onClick={() => field.onChange('')}>
                Clear
              </Button>
            )}
          </div>
          {projectDue && field.value > projectDue && (
            <FieldDescription className="flex items-center gap-1.5 text-warning">
              <TriangleAlertIcon className="size-3.5" />
              After the project deadline ({formatDateOnly(projectDue)}).
            </FieldDescription>
          )}
          <FieldError errors={[fieldState.error]} />
        </Field>
      )}
    />
  );
}
