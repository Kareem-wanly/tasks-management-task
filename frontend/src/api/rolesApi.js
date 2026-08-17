import apiClient from './apiClient';

const rolesApi = {

  getAll: async () => {
    return await apiClient.get('/roles');
  },


  getById: async (id) => {
    return await apiClient.get(`/roles/${id}`);
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


  getPermissions: async () => {
    return await apiClient.get('/permissions');
  },
};

export default rolesApi;