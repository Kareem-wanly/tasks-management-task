import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { TaskFile, TaskFiles } from '@/types/api';

interface TaskFilesBody {
  data: TaskFile[];
  meta: { stale: boolean; can_manage: boolean; max_kb: number };
}

const toFiles = (body: TaskFilesBody): TaskFiles => ({ files: body.data, ...body.meta });

/** Files attached to a task (Bitrix24 step 4.5). Writes answer with the whole list. */
const filesApi = {
  /** Fetched from Bitrix24 on each call: it sends no events for attachments. */
  get: async (taskId: number, options: RequestOptions = {}) =>
    toFiles(await apiClient.get<TaskFilesBody>(`/tasks/${taskId}/files`, options)),

  upload: async (taskId: number, file: File) => {
    const form = new FormData();
    form.append('file', file);
    return toFiles(await apiClient.post<TaskFilesBody>(`/tasks/${taskId}/files`, form));
  },

  /** Takes the file off the task (in Bitrix24 it stays on the uploader's Drive). */
  remove: async (fileId: number) => toFiles(await apiClient.delete<TaskFilesBody>(`/task-files/${fileId}`)),

  /** The file's bytes, streamed through our API with the employee's own Bitrix24 access. */
  download: (fileId: number): Promise<Blob> => apiClient.blob(`/task-files/${fileId}/download`),
};

export default filesApi;
