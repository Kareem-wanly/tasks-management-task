import apiClient from './apiClient';

const rolesApi = {

  getAll: async (params = {}, options = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams ? `/roles?${queryParams}` : '/roles';
    return await apiClient.get(endpoint, options);
  },


  getById: async (id, options = {}) => {
    return await apiClient.get(`/roles/${id}`, options);
  },


  create: async (roleData) => {
    return await apiClient.post('/roles', roleData);
  },


  update: async (id, roleData) => {
    return await apiClient.put(`/roles/${id}`, roleData);
  },


  delete: async (id) => {
    return await apiClient.delete(`/roles/${id}`);
  },


  getPermissions: async (options = {}) => {
    return await apiClient.get('/permissions', options);
  },
};

export default rolesApi;