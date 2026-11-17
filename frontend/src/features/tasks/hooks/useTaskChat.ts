import { useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import chatApi from '@/api/chatApi';
import { queryKeys } from '@/api/queryKeys';
import useApiMutation from '@/hooks/useApiMutation';
import { errorMessage } from '@/lib/apiClient';
import { showToast } from '@/lib/toast/ToastContext';
import type { TaskChat, TaskChatFile, TaskChatMessage } from '@/types/api';
import { saveBlob } from '@/utils/download';

/** While the chat is on screen, new messages from Bitrix24 show up within this time. */
const POLL_MS = 15_000;

type ChatChange =
  | { kind: 'send'; text: string }
  | { kind: 'update'; target: TaskChatMessage; text: string }
  | { kind: 'remove'; target: TaskChatMessage };

export interface TaskChatControl {
  query: UseQueryResult<TaskChat>;
  send: (text: string, onDone?: () => void) => void;
  update: (target: TaskChatMessage, text: string, onDone?: () => void) => void;
  remove: (target: TaskChatMessage, onDone?: () => void) => void;
  download: (message: TaskChatMessage, file: TaskChatFile) => Promise<void>;
  isPending: boolean;
}

/** A task's chat (Bitrix24 step 4.7), polled while the page is visible. */
export default function useTaskChat(taskId: number): TaskChatControl {
  const queryClient = useQueryClient();
  const key = queryKeys.tasks.chat(taskId);

  const query = useQuery({
    queryKey: key,
    queryFn: ({ signal }) => chatApi.get(taskId, { signal }),
    refetchInterval: POLL_MS,
  });

  const mutation = useApiMutation({
    mutationFn: (change: ChatChange & { onDone?: () => void }) => {
      switch (change.kind) {
        case 'send':
          return chatApi.send(taskId, change.text);
        case 'update':
          return chatApi.update(change.target.id, change.text);
        case 'remove':
          return chatApi.remove(change.target.id);
      }
    },
    // Sending is visible in the chat itself; only edits and deletes need a toast.
    successMessage: (_: TaskChat, change) => (change.kind === 'update' ? 'Message updated.' : change.kind === 'remove' ? 'Message deleted.' : ''),
    errorMessage: 'Failed to save the message.',
    onSuccess: (chat, change) => {
      queryClient.setQueryData<TaskChat>(key, chat);
      change.onDone?.();
    },
  });

  return {
    query,
    send: (text, onDone) => mutation.mutate({ kind: 'send', text, onDone }),
    update: (target, text, onDone) => mutation.mutate({ kind: 'update', target, text, onDone }),
    remove: (target, onDone) => mutation.mutate({ kind: 'remove', target, onDone }),
    download: async (message, file) => {
      try {
        saveBlob(await chatApi.download(message.id, file.id), file.name);
      } catch (err) {
        showToast(errorMessage(err, 'The file could not be downloaded.'), 'error');
      }
    },
    isPending: mutation.isPending,
  };
}
