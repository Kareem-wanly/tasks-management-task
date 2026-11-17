import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { TaskResult, TaskResults } from '@/types/api';

interface TaskResultsBody {
  data: TaskResult[];
  meta: { stale: boolean; can_add: boolean };
}

const toResults = (body: TaskResultsBody): TaskResults => ({ results: body.data, ...body.meta });

/** A task's official results (Bitrix24 step 4.6). Every call answers with the whole list. */
const resultsApi = {
  /** Fetched from Bitrix24 on each call: it sends no events for results. */
  get: async (taskId: number, options: RequestOptions = {}) =>
    toResults(await apiClient.get<TaskResultsBody>(`/tasks/${taskId}/results`, options)),

  add: async (taskId: number, text: string) =>
    toResults(await apiClient.post<TaskResultsBody>(`/tasks/${taskId}/results`, { text })),

  update: async (resultId: number, text: string) =>
    toResults(await apiClient.put<TaskResultsBody>(`/task-results/${resultId}`, { text })),

  remove: async (resultId: number) => toResults(await apiClient.delete<TaskResultsBody>(`/task-results/${resultId}`)),
};

export default resultsApi;
