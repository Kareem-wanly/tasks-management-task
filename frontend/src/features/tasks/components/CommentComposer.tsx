import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Loader2Icon, SendIcon } from 'lucide-react';
import commentsApi from '@/api/commentsApi';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import useApiMutation from '@/hooks/useApiMutation';
import { applyServerErrors } from '@/lib/forms';
import type { Comment } from '@/types/api';
import { commentSchema, type CommentFormValues } from '../schemas';

interface CommentComposerProps {
  taskId: number;
  onAdded: (comment: Comment) => void;
}

/** New comment box; Ctrl/⌘ + Enter posts. */
export default function CommentComposer({ taskId, onAdded }: CommentComposerProps) {
  const form = useForm<CommentFormValues>({ resolver: zodResolver(commentSchema), defaultValues: { body: '' } });

  const post = useApiMutation({
    mutationFn: (values: CommentFormValues) => commentsApi.create(taskId, { body: values.body }),
    successMessage: 'Comment posted.',
    errorMessage: 'Failed to post comment.',
    onSuccess: (comment) => {
      onAdded(comment);
      form.reset({ body: '' });
    },
    onError: (err) => applyServerErrors(err, form.setError),
  });

  const submit = form.handleSubmit((values) => post.mutate(values));
  const error = form.formState.errors.body;

  return (
    <form onSubmit={submit} noValidate className="mt-5 space-y-2 border-t border-border pt-5">
      <Field data-invalid={Boolean(error)}>
        <Textarea
          {...form.register('body')}
          rows={3}
          aria-label="New comment"
          aria-invalid={Boolean(error)}
          placeholder="Write a reply, update or feedback… (Markdown supported)"
          disabled={post.isPending}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
              e.preventDefault();
              void submit();
            }
          }}
        />
        <FieldError errors={[error]} />
      </Field>
      <div className="flex items-center justify-between gap-2">
        <span className="text-xs text-muted-foreground">Ctrl + Enter to post</span>
        <Button type="submit" variant="brand" size="sm" disabled={post.isPending}>
          {post.isPending ? <Loader2Icon className="animate-spin" /> : <SendIcon className="rtl:-scale-x-100" />}
          {post.isPending ? 'Posting…' : 'Post comment'}
        </Button>
      </div>
    </form>
  );
}
