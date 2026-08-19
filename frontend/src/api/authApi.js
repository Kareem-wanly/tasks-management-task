import apiClient from './apiClient';

const authApi = {
  login: async (credentials) => {
    return await apiClient.post('/login', credentials);
  },

  register: async (userData) => {
    return await apiClient.post('/register', userData);
  },

  logout: async () => {
    return await apiClient.post('/logout');
  },

  getCurrentUser: async () => {
    return await apiClient.get('/me');
  },

  updateProfile: async (profileData) => {
    return await apiClient.put('/me', profileData);
  },

  updatePassword: async (passwords) => {
    return await apiClient.put('/me/password', passwords);
  },
};

export default authApi;