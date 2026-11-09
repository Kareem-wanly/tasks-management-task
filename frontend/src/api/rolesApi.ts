import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { DataBody, Permission, Role } from '@/types/api';

export interface RoleInput {
  name: string;
  display_name: string;
  description: string | null;
  /** Permission ids. */
  permissions: number[];
}

const rolesApi = {
  getAll: async (options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<Role[]>>('/roles', options);
    return res.data;
  },

  getById: async (id: number | string, options: RequestOptions = {}) => {
    const res = await apiClient.get<{ role: Role }>(`/roles/${id}`, options);
    return res.role;
  },

  create: async (roleData: RoleInput) => {
    const res = await apiClient.post<DataBody<Role>>('/roles', { ...roleData });
    return res.data;
  },

  update: async (id: number | string, roleData: Partial<RoleInput>) => {
    const res = await apiClient.put<DataBody<Role>>(`/roles/${id}`, { ...roleData });
    return res.data;
  },

  delete: async (id: number) => {
    return await apiClient.delete(`/roles/${id}`);
  },

  getPermissions: async (options: RequestOptions = {}) => {
    const res = await apiClient.get<DataBody<Permission[]>>('/permissions', options);
    return res.data;
  },
};

export default rolesApi;
