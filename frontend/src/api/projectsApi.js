import apiClient from './apiClient';

const projectsApi = {
  /**
   * جلب قائمة المشاريع مع دعم الفلاتر والبحث والترقيم
   * @param {Object} params - { page, search, status, sort_by }
   */
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
};

export default projectsApi;