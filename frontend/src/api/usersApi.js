import apiClient from './apiClient';

const usersApi = {

  getAll: async (params = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams ? `/users?${queryParams}` : '/users';
    return await apiClient.get(endpoint);
  },


  getById: async (id) => {
    return await apiClient.get(`/users/${id}`);
  },


  create: async (userData) => {
    return await apiClient.post('/users', userData);
  },


  update: async (id, userData) => {
    return await apiClient.put(`/users/${id}`, userData);
  },


  delete: async (id) => {
    return await apiClient.delete(`/users/${id}`);
  },

  getUserRoles: async (id) => {
    return await apiClient.get(`/users/${id}/roles`);
  },

  syncRoles: async (id, roles) => {
    return await apiClient.put(`/users/${id}/roles`, { roles });
  },

};

export default usersApi;