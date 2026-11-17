import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { TaskHistory, TaskHistoryEntry, TaskHistoryUnavailable } from '@/types/api';

interface TaskHistoryBody {
  data: TaskHistoryEntry[];
  meta: { unavailable_because: TaskHistoryUnavailable | null };
}

/** A task's change history from Bitrix24 (step 4.8). Read only. */
const historyApi = {
  get: async (taskId: number, options: RequestOptions = {}): Promise<TaskHistory> => {
    const body = await apiClient.get<TaskHistoryBody>(`/tasks/${taskId}/history`, options);
    return { entries: body.data, unavailableBecause: body.meta.unavailable_because };
  },
};

export default historyApi;
