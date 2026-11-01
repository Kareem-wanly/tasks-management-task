import apiClient from './apiClient';
import { toPage } from './paginate';

const activitiesApi = {

  getAll: async (params = {}, options = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams ? `/activities?${queryParams}` : '/activities';
    return toPage(await apiClient.get(endpoint, options));
  },

  getByProject: async (projectId, params = {}, options = {}) => {
    const queryParams = new URLSearchParams(params).toString();
    const endpoint = queryParams
      ? `/projects/${projectId}/activities?${queryParams}`
      : `/projects/${projectId}/activities`;
    return toPage(await apiClient.get(endpoint, options));
  },
};

export default activitiesApi;
