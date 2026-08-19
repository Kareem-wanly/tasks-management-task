import apiClient from './apiClient';

const activitiesApi = {

  getAll: async (params = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams ? `/activities?${queryParams}` : '/activities';
    return await apiClient.get(endpoint);
  },


  getByProject: async (projectId) => {
    return await apiClient.get(`/projects/${projectId}/activities`);
  },



  getByTask: async (taskId) => {
    return await apiClient.get(`/tasks/${taskId}/activities`);
  },
};

export default activitiesApi;