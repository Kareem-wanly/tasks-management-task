import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Loader2Icon, SendIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { chatMessageSchema, type ChatMessageFormValues } from '../schemas';

interface TaskChatFormProps {
  /** The current text when editing; empty for a new message. */
  initial?: string;
  pending: boolean;
  onSubmit: (text: string, reset: () => void) => void;
  /** Editing: shows Cancel, and Escape cancels too. */
  onCancel?: () => void;
}

/** A message's text; Enter sends, Shift + Enter starts a new line (as in Bitrix24). */
export default function TaskChatForm({ initial = '', pending, onSubmit, onCancel }: TaskChatFormProps) {
  const form = useForm<ChatMessageFormValues>({ resolver: zodResolver(chatMessageSchema), defaultValues: { text: initial } });
  const error = form.formState.errors.text;
  const submit = form.handleSubmit((values) => onSubmit(values.text, () => form.reset({ text: '' })));
  const editing = onCancel !== undefined;

  return (
    <form onSubmit={submit} noValidate className="space-y-2">
      <Field data-invalid={Boolean(error)}>
        <Textarea
          {...form.register('text')}
          rows={editing ? 3 : 2}
          autoFocus={editing}
          aria-label={editing ? 'Edit message' : 'New message'}
          aria-invalid={Boolean(error)}
          placeholder={editing ? undefined : 'Write a message… (Enter to send, Shift + Enter for a new line)'}
          disabled={pending}
          onKeyDown={(e) => {
            if (e.key === 'Escape' && onCancel) {
              e.preventDefault();
              onCancel();
            } else if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
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
        <Button type="submit" variant="brand" size="sm" disabled={pending}>
          {pending ? <Loader2Icon className="animate-spin" /> : !editing && <SendIcon className="rtl:-scale-x-100" />}
          {editing ? 'Save' : 'Send'}
        </Button>
      </div>
    </form>
  );
}
