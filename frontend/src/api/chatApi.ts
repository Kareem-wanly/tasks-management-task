import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { TaskChat, TaskChatMessage } from '@/types/api';

interface TaskChatBody {
  data: TaskChatMessage[];
  meta: { stale: boolean; can_post: boolean };
}

const toChat = (body: TaskChatBody): TaskChat => ({ messages: body.data, ...body.meta });

/** A task's chat (Bitrix24 step 4.7; replaced comments). Every call answers with the newest messages. */
const chatApi = {
  /** Also brings in edits and deletes made in Bitrix24, which it sends no events for. */
  get: async (taskId: number, options: RequestOptions = {}) =>
    toChat(await apiClient.get<TaskChatBody>(`/tasks/${taskId}/chat`, options)),

  send: async (taskId: number, text: string) => toChat(await apiClient.post<TaskChatBody>(`/tasks/${taskId}/chat`, { text })),

  update: async (messageId: number, text: string) =>
    toChat(await apiClient.put<TaskChatBody>(`/task-chat-messages/${messageId}`, { text })),

  remove: async (messageId: number) => toChat(await apiClient.delete<TaskChatBody>(`/task-chat-messages/${messageId}`)),

  /** A file sent in the chat in Bitrix24, streamed through our API with the employee's own access. */
  download: (messageId: number, fileId: number): Promise<Blob> =>
    apiClient.blob(`/task-chat-messages/${messageId}/files/${fileId}/download`),
};

export default chatApi;
