import apiClient from './apiClient';

const tasksApi = {

  getAll: async (params = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams ? `/tasks?${queryParams}` : '/tasks';
    return await apiClient.get(endpoint);
  },

  getById: async (id) => {
    return await apiClient.get(`/tasks/${id}`);
  },


  create: async (taskData) => {
    return await apiClient.post('/tasks', taskData);
  },


  update: async (id, taskData) => {
    return await apiClient.put(`/tasks/${id}`, taskData);
  },


  updateStatus: async (id, status) => {
    return await apiClient.patch(`/tasks/${id}/status`, { status });
  },


  delete: async (id) => {
    return await apiClient.delete(`/tasks/${id}`);
  },
};

export default tasksApi;