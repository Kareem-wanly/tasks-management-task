import type { ComponentProps } from 'react';
import { Controller, type Control, type FieldPath, type FieldValues } from 'react-hook-form';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';

interface TextareaFieldProps<T extends FieldValues>
  extends Omit<ComponentProps<'textarea'>, 'name' | 'value' | 'defaultValue' | 'onChange' | 'onBlur'> {
  control: Control<T>;
  name: FieldPath<T>;
  label: string;
  description?: string;
}

/** Label + textarea + validation error, bound to a react-hook-form field. */
export default function TextareaField<T extends FieldValues>({
  control,
  name,
  label,
  description,
  id,
  ...textareaProps
}: TextareaFieldProps<T>) {
  const inputId = id ?? name;

  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <Field data-invalid={fieldState.invalid}>
          <FieldLabel htmlFor={inputId}>{label}</FieldLabel>
          <Textarea
            {...textareaProps}
            {...field}
            id={inputId}
            value={String(field.value ?? '')}
            aria-invalid={fieldState.invalid}
          />
          {description && <FieldDescription>{description}</FieldDescription>}
          <FieldError errors={[fieldState.error]} />
        </Field>
      )}
    />
  );
}
