import apiClient from './apiClient';

const authApi = {
  
  login: async (credentials) => {
    return await apiClient.post('/login', credentials);
  },

  logout: async () => {
    return await apiClient.post('/logout');
  },


  getCurrentUser: async () => {
    return await apiClient.get('/me');
  },
};

export default authApi;