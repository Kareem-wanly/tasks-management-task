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
  /** Makes it a subtask of that task; null takes it out of its parent. */
  parent_id?: number | null;
  allow_time_tracking?: boolean;
  /** Planned time in seconds. */
  time_estimate?: number;
}

export interface TaskMembersInput {
  accomplice_ids: number[];
  auditor_ids: number[];
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

  /** `projectId` null creates a task without a project (a Bitrix24 personal task). */
  create: async (projectId: number | string | null, taskData: TaskInput) => {
    const endpoint = projectId === null ? '/tasks' : `/projects/${projectId}/tasks`;
    const res = await apiClient.post<DataBody<Task>>(endpoint, { ...taskData });
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

  /** Replaces both lists; an empty list removes everyone from it. */
  setMembers: async (id: number, members: TaskMembersInput) => {
    const res = await apiClient.put<DataBody<Task>>(`/tasks/${id}/members`, { ...members });
    return res.data;
  },

  /** The signed-in user starts (true) or stops following the task. */
  watch: async (id: number, watching: boolean) => {
    const res = watching
      ? await apiClient.post<DataBody<Task>>(`/tasks/${id}/watch`, {})
      : await apiClient.delete<DataBody<Task>>(`/tasks/${id}/watch`);
    return res.data;
  },

  /** Only the assignee may hand the task over; Bitrix makes them an auditor unless they created it. */
  delegate: async (id: number, userId: number) => {
    const res = await apiClient.post<DataBody<Task>>(`/tasks/${id}/delegate`, { user_id: userId });
    return res.data;
  },

  delete: async (id: number) => {
    return await apiClient.delete(`/tasks/${id}`);
  },
};

export default tasksApi;
