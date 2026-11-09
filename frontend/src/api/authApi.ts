import apiClient from '@/lib/apiClient';
import type { AuthResponse, User } from '@/types/api';

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface ProfileInput {
  name: string;
  email: string;
}

export interface PasswordInput {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface RegisterData extends LoginCredentials {
  name: string;
  password_confirmation: string;
}

const authApi = {
  login: async (credentials: LoginCredentials) => {
    return await apiClient.post<AuthResponse>('/login', { ...credentials });
  },

  register: async (userData: RegisterData) => {
    return await apiClient.post<AuthResponse>('/register', { ...userData });
  },

  logout: async () => {
    return await apiClient.post('/logout');
  },

  updateProfile: async (data: ProfileInput) => {
    return await apiClient.put<{ message?: string }>('/me', { ...data });
  },

  updatePassword: async (data: PasswordInput) => {
    return await apiClient.put<{ message?: string }>('/me/password', { ...data });
  },

  getCurrentUser: async () => {
    const res = await apiClient.get<{ user: User }>('/me');
    return res.user;
  },
};

export default authApi;
