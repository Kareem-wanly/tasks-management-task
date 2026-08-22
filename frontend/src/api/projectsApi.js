import apiClient from './apiClient';

const projectsApi = {
  getAll: async (params = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams ? `/projects?${queryParams}` : '/projects';
    return await apiClient.get(endpoint);
  },

  getById: async (id) => {
    return await apiClient.get(`/projects/${id}`);
  },

  create: async (projectData) => {
    return await apiClient.post('/projects', projectData);
  },

  update: async (id, projectData) => {
    return await apiClient.put(`/projects/${id}`, projectData);
  },

  delete: async (id) => {
    return await apiClient.delete(`/projects/${id}`);
  },

  archive: (id) => apiClient.patch(`/projects/${id}/archive`),

  addMember: async (projectId, data) => {
    return await apiClient.post(`/projects/${projectId}/members`, data);
  },

  removeMember: async (projectId, userId) => {
    return await apiClient.delete(`/projects/${projectId}/members/${userId}`);
  },
};

export default projectsApi;