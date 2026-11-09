import apiClient, { type RequestOptions } from '@/lib/apiClient';
import { toPage, toQuery, type QueryParams } from '@/lib/paginate';
import type { DataBody, PaginatedBody, Task, TaskPriority, TaskStatus } from '@/types/api';

export interface TaskInput {
  title: string;
  description?: string | null;
  status?: TaskStatus;
  priority?: TaskPriority;
  due_date?: string | null;
  /** Leave out to keep the current assignee; null unassigns. */
  assigned_to?: number | null;
}

const tasksApi = {
  getAll: async (params: QueryParams = {}, options: RequestOptions = {}) => {
    const res = await apiClient.get<PaginatedBody<Task>>(`/tasks${toQuery(params)}`, options);
    return toPage(res);
  },

  getById: async (id: number | string, options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<Task>>(`/tasks/${id}`, options);
    return res.data;
  },

  create: async (projectId: number | string, taskData: TaskInput) => {
    const res = await apiClient.post<DataBody<Task>>(`/projects/${projectId}/tasks`, { ...taskData });
    return res.data;
  },

  update: async (id: number, taskData: Partial<TaskInput>) => {
    const res = await apiClient.put<DataBody<Task>>(`/tasks/${id}`, { ...taskData });
    return res.data;
  },

  updateStatus: async (id: number, status: TaskStatus) => {
    const res = await apiClient.patch<DataBody<Task>>(`/tasks/${id}/status`, { status });
    return res.data;
  },

  delete: async (id: number) => {
    return await apiClient.delete(`/tasks/${id}`);
  },
};

export default tasksApi;
