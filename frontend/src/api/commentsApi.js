import apiClient from './apiClient';

const commentsApi = {

  getByTask: async (taskId) => {
    return await apiClient.get(`/tasks/${taskId}/comments`);
  },


  create: async (taskId, commentData) => {
    return await apiClient.post(`/tasks/${taskId}/comments`, commentData);
  },


  update: async (commentId, commentData) => {
    return await apiClient.put(`/comments/${commentId}`, commentData);
  },


  delete: async (commentId) => {
    return await apiClient.delete(`/comments/${commentId}`);
  },
};

export default commentsApi;