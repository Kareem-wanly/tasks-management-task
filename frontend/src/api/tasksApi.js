import apiClient from './apiClient';

const tasksApi = {
  getAll: async (params = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams ? `/tasks?${queryParams}` : '/tasks';
    return await apiClient.get(endpoint);
  },

  getByProject: async (projectId) => {
    return await apiClient.get(`/projects/${projectId}/tasks`);
  },

  getById: async (id) => {
    return await apiClient.get(`/tasks/${id}`);
  },


  create: async (projectId, taskData) => {
    return await apiClient.post(`/projects/${projectId}/tasks`, taskData);
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

  addComment: async (taskId, body) => {
    return await apiClient.post(`/tasks/${taskId}/comments`, { body });
  },

  deleteComment: async (commentId) => {
    return await apiClient.delete(`/comments/${commentId}`);
  }

};

export default tasksApi;