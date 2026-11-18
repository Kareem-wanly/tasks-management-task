import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { DataBody, TaskPriorityOption, TaskStatusOption } from '@/types/api';

/** Task statuses and priorities, ordered by `sort` on the server. */
const taskLookupsApi = {
  getStatuses: async (options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<TaskStatusOption[]>>('/task-statuses', options);
    return res.data;
  },

  getPriorities: async (options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<TaskPriorityOption[]>>('/task-priorities', options);
    return res.data;
  },
};

export default taskLookupsApi;
