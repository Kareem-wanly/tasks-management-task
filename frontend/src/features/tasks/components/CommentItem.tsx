import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { PencilIcon, Trash2Icon } from 'lucide-react';
import commentsApi from '@/api/commentsApi';
import Markdown from '@/components/data/Markdown';
import UserAvatar from '@/components/data/UserAvatar';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import useApiMutation from '@/hooks/useApiMutation';
import { applyServerErrors } from '@/lib/forms';
import { formatDateTime } from '@/utils/format';
import type { Comment } from '@/types/api';
import { commentSchema, type CommentFormValues } from '../schemas';

interface CommentItemProps {
  comment: Comment;
  isOwner: boolean;
  canEdit: boolean;
  canDelete: boolean;
  /** Only one comment is edited at a time; the list decides which. */
  editing: boolean;
  /** Another comment is being edited, so this one hides its edit button. */
  editLocked: boolean;
  onStartEdit: () => void;
  onStopEdit: () => void;
  onUpdated: (comment: Comment) => void;
  onDelete: () => void;
}

export default function CommentItem({
  comment,
  isOwner,
  canEdit,
  canDelete,
  editing,
  editLocked,
  onStartEdit,
  onStopEdit,
  onUpdated,
  onDelete,
}: CommentItemProps) {
  const authorName = comment.user.name || 'Team member';
  const form = useForm<CommentFormValues>({ resolver: zodResolver(commentSchema), values: { body: comment.body } });

  const save = useApiMutation({
    mutationFn: (values: CommentFormValues) => commentsApi.update(comment.id, { body: values.body }),
    successMessage: 'Comment updated.',
    errorMessage: 'Failed to update comment.',
    onSuccess: (updated) => {
      onUpdated(updated);
      onStopEdit();
    },
    onError: (err) => applyServerErrors(err, form.setError),
  });

  const submit = form.handleSubmit((values) => save.mutate(values));
  const cancel = () => {
    form.reset({ body: comment.body });
    onStopEdit();
  };
  const error = form.formState.errors.body;

  return (
    <li className="flex gap-3 py-4 first:pt-0 last:pb-0">
      <UserAvatar user={{ id: comment.user.id, name: authorName }} size="md" />

      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span className="text-[13px] font-medium">{authorName}</span>
          {isOwner && <span className="text-xs text-muted-foreground">(You)</span>}
          <span className="text-xs text-muted-foreground tabular-nums">{formatDateTime(comment.created_at)}</span>

          <div className="ms-auto flex gap-1">
            {canEdit && !editing && !editLocked && (
              <Button variant="ghost" size="icon-xs" onClick={onStartEdit} aria-label="Edit comment">
                <PencilIcon />
              </Button>
            )}
            {canDelete && !editing && (
              <Button variant="ghost" size="icon-xs" onClick={onDelete} aria-label="Delete comment">
                <Trash2Icon className="text-destructive" />
              </Button>
            )}
          </div>
        </div>

        {editing ? (
          <form onSubmit={submit} noValidate className="mt-2 space-y-2">
            <Field data-invalid={Boolean(error)}>
              <Textarea
                {...form.register('body')}
                autoFocus
                rows={3}
                aria-label="Edit comment"
                aria-invalid={Boolean(error)}
                onKeyDown={(e) => {
                  if (e.key === 'Escape') {
                    e.preventDefault();
                    cancel();
                  } else if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                    e.preventDefault();
                    void submit();
                  }
                }}
              />
              <FieldError errors={[error]} />
            </Field>
            <div className="flex gap-2">
              <Button type="submit" size="sm" variant="brand" disabled={save.isPending}>
                {save.isPending ? 'Saving…' : 'Save'}
              </Button>
              <Button type="button" size="sm" variant="ghost" onClick={cancel}>
                Cancel
              </Button>
            </div>
          </form>
        ) : (
          <Markdown className="mt-1">{comment.body}</Markdown>
        )}
      </div>
    </li>
  );
}
