import apiClient from './apiClient';
import { toPage } from './paginate';

const tasksApi = {
  getAll: async (params = {}, options = {}) => {
    const cleanedParams = Object.entries(params).reduce((acc, [key, val]) => {
      if (val !== undefined && val !== null && val !== '') {
        acc[key] = val;
      }
      return acc;
    }, {});

    const queryParams = new URLSearchParams(cleanedParams).toString();
    const endpoint = queryParams ? `/tasks?${queryParams}` : '/tasks';
    const res = await apiClient.get(endpoint, options);
    return toPage(res);
  },

  getByProject: async (projectId, params = {}, options = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams 
      ? `/projects/${projectId}/tasks?${queryParams}` 
      : `/projects/${projectId}/tasks`;
    const res = await apiClient.get(endpoint, options);
    return toPage(res);
  },

  getById: async (id, options = {}) => {
    const res = await apiClient.get(`/tasks/${id}`, options);
    return res?.data ?? res;
  },

  create: async (projectId, taskData) => {
    const res = await apiClient.post(`/projects/${projectId}/tasks`, taskData);
    return res?.data ?? res;
  },

  update: async (id, taskData) => {
    const res = await apiClient.put(`/tasks/${id}`, taskData);
    return res?.data ?? res;
  },

  updateStatus: async (id, status) => {
    const res = await apiClient.patch(`/tasks/${id}/status`, { status });
    return res?.data ?? res;
  },

  delete: async (id) => {
    return await apiClient.delete(`/tasks/${id}`);
  },

  addComment: async (taskId, body) => {
    const res = await apiClient.post(`/tasks/${taskId}/comments`, { body });
    return res?.data ?? res;
  },

  deleteComment: async (commentId) => {
    return await apiClient.delete(`/comments/${commentId}`);
  },
};

export default tasksApi;