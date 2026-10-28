import apiClient from './apiClient';
import { toPage } from './paginate';

const projectsApi = {
  getAll: async (params = {}, options = {}) => {
    const cleanedParams = Object.entries(params).reduce((acc, [key, val]) => {
      if (val !== undefined && val !== null && val !== '') {
        acc[key] = val;
      }
      return acc;
    }, {});

    const queryParams = new URLSearchParams(cleanedParams).toString();
    const endpoint = queryParams ? `/projects?${queryParams}` : '/projects';
    const res = await apiClient.get(endpoint, options);
    return toPage(res);
  },

  options: async (params = {}, options = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams ? `/projects/options?${queryParams}` : '/projects/options';
    const res = await apiClient.get(endpoint, options);
    return res?.data ?? res;
  },

  getById: async (id, options = {}) => {
    const res = await apiClient.get(`/projects/${id}`, options);
    return res?.data ?? res;
  },

  create: async (projectData) => {
    const res = await apiClient.post('/projects', projectData);
    return res?.data ?? res;
  },

  update: async (id, projectData) => {
    const res = await apiClient.put(`/projects/${id}`, projectData);
    return res?.data ?? res;
  },

  delete: async (id) => {
    return await apiClient.delete(`/projects/${id}`);
  },

  archive: async (id) => {
    const res = await apiClient.patch(`/projects/${id}/archive`);
    return res?.data ?? res;
  },

  addMember: async (projectId, data) => {
    const res = await apiClient.post(`/projects/${projectId}/members`, data);
    return res?.data ?? res;
  },

  removeMember: async (projectId, userId) => {
    return await apiClient.delete(`/projects/${projectId}/members/${userId}`);
  },
};

export default projectsApi;