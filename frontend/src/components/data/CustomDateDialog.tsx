import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import TextField from '@/components/form/TextField';
import FormDialog from '@/components/ui/FormDialog';
import { formatDateOnly } from '@/utils/format';

interface CustomDateDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  initialDate: string;
  /** Earliest allowed date, e.g. a project's start date. */
  minDate?: string | null;
  onSubmit: (date: string) => void;
}

const dateSchema = (minDate: string | null | undefined) =>
  z
    .object({ date: z.string().min(1, 'Pick a date.') })
    .refine((values) => !minDate || values.date >= minDate, {
      path: ['date'],
      message: `Pick a date on or after ${formatDateOnly(minDate)}.`,
    });

type DateFormValues = z.infer<ReturnType<typeof dateSchema>>;

/** One-field dialog for picking a specific date. */
export default function CustomDateDialog({
  open,
  onOpenChange,
  title,
  initialDate,
  minDate,
  onSubmit,
}: CustomDateDialogProps) {
  const form = useForm<DateFormValues>({
    resolver: zodResolver(dateSchema(minDate)),
    defaultValues: { date: initialDate },
  });

  useEffect(() => {
    if (open) form.reset({ date: initialDate });
  }, [open, initialDate, form]);

  return (
    <FormDialog
      open={open}
      onOpenChange={onOpenChange}
      title={title}
      submitLabel="Set date"
      onSubmit={form.handleSubmit((values) => {
        onSubmit(values.date);
        onOpenChange(false);
      })}
    >
      <TextField control={form.control} name="date" label="Due date" type="date" min={minDate ?? undefined} autoFocus />
    </FormDialog>
  );
}
