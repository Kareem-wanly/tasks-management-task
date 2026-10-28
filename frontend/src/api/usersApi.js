import apiClient from './apiClient';
import { toPage } from './paginate';

const usersApi = {
  getAll: async (params = {}, options = {}) => {
    const cleanedParams = Object.entries(params).reduce((acc, [key, val]) => {
      if (val !== undefined && val !== null && val !== '') {
        acc[key] = val;
      }
      return acc;
    }, {});

    const queryParams = new URLSearchParams(cleanedParams).toString();
    const endpoint = queryParams ? `/users?${queryParams}` : '/users';
    const res = await apiClient.get(endpoint, options);
    return toPage(res);
  },

  options: async (params = {}, options = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams ? `/users/options?${queryParams}` : '/users/options';
    const res = await apiClient.get(endpoint, options);
    return res?.data ?? res;
  },

  getById: async (id, options = {}) => {
    const res = await apiClient.get(`/users/${id}`, options);
    return res?.data ?? res;
  },

  create: async (userData) => {
    const res = await apiClient.post('/users', userData);
    return res?.data ?? res;
  },

  update: async (id, userData) => {
    const res = await apiClient.put(`/users/${id}`, userData);
    return res?.data ?? res;
  },

  delete: async (id) => {
    return await apiClient.delete(`/users/${id}`);
  },

  getUserRoles: async (id, options = {}) => {
    const res = await apiClient.get(`/users/${id}/roles`, options);
    return res?.data ?? res;
  },

  syncRoles: async (id, roles) => {
    const res = await apiClient.put(`/users/${id}/roles`, { roles });
    return res?.data ?? res;
  },
};

export default usersApi;