import { useState } from 'react';
import { MessageSquareIcon } from 'lucide-react';
import commentsApi from '@/api/commentsApi';
import EmptyState from '@/components/data/EmptyState';
import Panel from '@/components/layout/Panel';
import ConfirmModal from '@/components/ui/ConfirmModal';
import useApiMutation from '@/hooks/useApiMutation';
import { useAuth } from '@/lib/auth/AuthContext';
import type { Comment } from '@/types/api';
import CommentComposer from './CommentComposer';
import CommentItem from './CommentItem';

interface TaskCommentsProps {
  taskId: number;
  comments: Comment[];
  /** Updates the comments held in the task query cache. */
  onCommentsChange: (update: (comments: Comment[]) => Comment[]) => void;
}

export default function TaskComments({ taskId, comments, onCommentsChange }: TaskCommentsProps) {
  const { user, can } = useAuth();
  const [editingId, setEditingId] = useState<number | null>(null);
  const [toDelete, setToDelete] = useState<Comment | null>(null);
  const canManageAll = can('comments.manage_all');

  const remove = useApiMutation({
    mutationFn: (comment: Comment) => commentsApi.delete(comment.id),
    successMessage: 'Comment deleted.',
    errorMessage: 'Failed to delete comment.',
    onSuccess: (_, comment) => {
      onCommentsChange((prev) => prev.filter((c) => c.id !== comment.id));
      setToDelete(null);
    },
  });

  return (
    <Panel
      title="Comments"
      actions={
        <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums">
          {comments.length}
        </span>
      }
    >
      {comments.length === 0 ? (
        <EmptyState icon={MessageSquareIcon} title="No comments yet" description="Start the conversation." className="py-8" />
      ) : (
        <ul className="divide-y divide-border">
          {comments.map((comment) => {
            const isOwner = user?.id === comment.user.id;
            return (
              <CommentItem
                key={comment.id}
                comment={comment}
                isOwner={isOwner}
                canEdit={(isOwner && can('comments.update')) || canManageAll}
                canDelete={(isOwner && can('comments.delete')) || canManageAll}
                editing={editingId === comment.id}
                editLocked={editingId !== null && editingId !== comment.id}
                onStartEdit={() => setEditingId(comment.id)}
                onStopEdit={() => setEditingId(null)}
                onUpdated={(updated) => onCommentsChange((prev) => prev.map((c) => (c.id === updated.id ? updated : c)))}
                onDelete={() => setToDelete(comment)}
              />
            );
          })}
        </ul>
      )}

      {can('comments.create') ? (
        <CommentComposer taskId={taskId} onAdded={(added) => onCommentsChange((prev) => [...prev, added])} />
      ) : (
        <p className="mt-5 border-t border-border pt-5 text-[13px] text-muted-foreground">
          You don't have permission to add comments.
        </p>
      )}

      <ConfirmModal
        isOpen={toDelete !== null}
        title="Delete comment"
        message="Are you sure you want to delete this comment?"
        confirmText="Delete"
        danger
        loading={remove.isPending}
        onConfirm={() => toDelete && remove.mutate(toDelete)}
        onClose={() => setToDelete(null)}
      />
    </Panel>
  );
}
