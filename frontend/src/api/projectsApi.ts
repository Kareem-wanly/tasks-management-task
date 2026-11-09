import apiClient, { type RequestOptions } from '@/lib/apiClient';
import { toPage, toQuery, type QueryParams } from '@/lib/paginate';
import type { DataBody, PaginatedBody, Project, ProjectOption, ProjectStatus } from '@/types/api';

export interface ProjectInput {
  title: string;
  description?: string | null;
  status?: Exclude<ProjectStatus, 'archived'>;
  start_date?: string | null;
  due_date?: string | null;
}

const projectsApi = {
  getAll: async (params: QueryParams = {}, options: RequestOptions = {}) => {
    const res = await apiClient.get<PaginatedBody<Project>>(`/projects${toQuery(params)}`, options);
    return toPage(res);
  },

  options: async (params: { include_archived?: 0 | 1 } = {}, options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<ProjectOption[]>>(`/projects/options${toQuery(params)}`, options);
    return res.data;
  },

  getById: async (id: number | string, options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<Project>>(`/projects/${id}`, options);
    return res.data;
  },

  create: async (projectData: ProjectInput) => {
    const res = await apiClient.post<DataBody<Project>>('/projects', { ...projectData });
    return res.data;
  },

  update: async (id: number, projectData: Partial<ProjectInput>) => {
    const res = await apiClient.put<DataBody<Project>>(`/projects/${id}`, { ...projectData });
    return res.data;
  },

  delete: async (id: number | string) => {
    return await apiClient.delete(`/projects/${id}`);
  },

  /** Toggles: archives an active project, unarchives an archived one. */
  archive: async (id: number | string) => {
    const res = await apiClient.patch<DataBody<Project>>(`/projects/${id}/archive`);
    return res.data;
  },

  addMember: async (projectId: number, data: { user_id: number }) => {
    const res = await apiClient.post<DataBody<Project>>(`/projects/${projectId}/members`, data);
    return res.data;
  },

  removeMember: async (projectId: number, userId: number) => {
    return await apiClient.delete(`/projects/${projectId}/members/${userId}`);
  },
};

export default projectsApi;
