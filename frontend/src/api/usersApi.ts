import apiClient, { type RequestOptions } from '@/lib/apiClient';
import { toPage, toQuery, type QueryParams } from '@/lib/paginate';
import type { DataBody, PaginatedBody, RoleRef, UserListItem, UserOption } from '@/types/api';

export interface CreateUserInput {
  name: string;
  email: string;
  password: string;
  role_id?: number;
}

export interface UpdateUserInput {
  name?: string;
  email?: string;
}

const usersApi = {
  getAll: async (params: QueryParams = {}, options: RequestOptions = {}) => {
    const res = await apiClient.get<PaginatedBody<UserListItem>>(`/users${toQuery(params)}`, options);
    return toPage(res);
  },

  options: async (options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<UserOption[]>>('/users/options', options);
    return res.data;
  },

  getById: async (id: number, options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<UserListItem>>(`/users/${id}`, options);
    return res.data;
  },

  create: async (userData: CreateUserInput) => {
    const res = await apiClient.post<DataBody<UserListItem>>('/users', { ...userData });
    return res.data;
  },

  /** The backend only accepts name and email here; roles go through syncRoles. */
  update: async (id: number, userData: UpdateUserInput) => {
    const res = await apiClient.put<DataBody<UserListItem>>(`/users/${id}`, { ...userData });
    return res.data;
  },

  delete: async (id: number) => {
    return await apiClient.delete(`/users/${id}`);
  },

  getUserRoles: async (id: number, options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<RoleRef[]>>(`/users/${id}/roles`, options);
    return res.data;
  },

  syncRoles: async (id: number, roles: number[]) => {
    const res = await apiClient.put<{ message: string; user: UserListItem }>(`/users/${id}/roles`, { roles });
    return res.user;
  },
};

export default usersApi;
