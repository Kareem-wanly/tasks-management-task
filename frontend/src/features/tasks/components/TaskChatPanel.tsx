import { useEffect, useRef, useState } from 'react';
import { CloudOffIcon, MessageSquareIcon } from 'lucide-react';
import EmptyState from '@/components/data/EmptyState';
import Panel from '@/components/layout/Panel';
import ConfirmModal from '@/components/ui/ConfirmModal';
import { Skeleton } from '@/components/ui/skeleton';
import { errorMessage } from '@/lib/apiClient';
import { useAuth } from '@/lib/auth/AuthContext';
import type { TaskChatMessage } from '@/types/api';
import useTaskChat from '../hooks/useTaskChat';
import TaskChatForm from './TaskChatForm';
import TaskChatMessageItem from './TaskChatMessageItem';

interface TaskChatPanelProps {
  taskId: number;
}

/** The task chat (Bitrix24 step 4.7; replaced comments): the same conversation as the task's chat in Bitrix24. */
export default function TaskChatPanel({ taskId }: TaskChatPanelProps) {
  const { user } = useAuth();
  const chat = useTaskChat(taskId);
  const { data, isPending, isError, error } = chat.query;
  const [toDelete, setToDelete] = useState<TaskChatMessage | null>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const lastId = data?.messages.at(-1)?.id;

  // Keep the newest message in view when one arrives.
  useEffect(() => {
    const list = listRef.current;
    if (list) list.scrollTop = list.scrollHeight;
  }, [lastId]);

  return (
    <Panel title="Chat" description={data && data.messages.length > 0 ? `${data.messages.length} messages` : undefined}>
      {isPending && <Skeleton className="h-24 w-full" />}
      {isError && <p className="text-[13px] text-danger">{errorMessage(error, "Couldn't load the chat.")}</p>}
      {data?.stale && (
        <p className="mb-2 flex items-center gap-1.5 text-xs text-muted-foreground">
          <CloudOffIcon className="size-3.5" />
          Bitrix24 is unreachable; this may be out of date.
        </p>
      )}
      {data && data.messages.length === 0 && (
        <EmptyState icon={MessageSquareIcon} title="No messages yet" description="Start the conversation." className="py-8" />
      )}
      {data && data.messages.length > 0 && (
        <ul ref={listRef} className="mb-3 max-h-[28rem] overflow-y-auto pe-1">
          {data.messages.map((message) => (
            <TaskChatMessageItem
              key={message.id}
              message={message}
              isOwn={message.author?.id === user?.id}
              chat={chat}
              onDelete={() => setToDelete(message)}
            />
          ))}
        </ul>
      )}
      {data?.can_post ? (
        <TaskChatForm pending={chat.isPending} onSubmit={(text, reset) => chat.send(text, reset)} />
      ) : (
        data && <p className="border-t border-border pt-3 text-[13px] text-muted-foreground">You can't write in this chat.</p>
      )}

      <ConfirmModal
        isOpen={toDelete !== null}
        title="Delete message"
        message="Delete this message? It is deleted in Bitrix24 too."
        confirmText="Delete"
        danger
        loading={chat.isPending}
        onConfirm={() => toDelete && chat.remove(toDelete, () => setToDelete(null))}
        onClose={() => setToDelete(null)}
      />
    </Panel>
  );
}
