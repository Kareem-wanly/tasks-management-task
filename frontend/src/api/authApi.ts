import apiClient, { BASE_URL } from '@/lib/apiClient';
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

/** What Bitrix24's JS SDK (BX24.getAuth()) gives a page opened inside Bitrix. */
export interface BitrixFrameAuth {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  domain: string;
  member_id: string;
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

  /**
   * Trades the one-time code from a Bitrix24 sign-in for an API token. It works once, within a minute,
   * and only with the nonce this tab made when the sign-in started.
   */
  exchangeBitrixCode: async (code: string, nonce: string): Promise<string> => {
    const res = await apiClient.post<{ token: string }>('/auth/bitrix/exchange', { code, nonce });
    return res.token;
  },

  /** The app opened inside Bitrix24: the tokens Bitrix's JS SDK gave this page, for an API token. */
  bitrixAppSession: async (auth: BitrixFrameAuth): Promise<string> => {
    const res = await apiClient.post<{ token: string }>('/auth/bitrix/app-session', { ...auth });
    return res.token;
  },

  getCurrentUser: async () => {
    const res = await apiClient.get<{ user: User }>('/me');
    return res.user;
  },

  /** Full-page navigation target: the OAuth start lives in Laravel's web routes, outside /api/v1. */
  bitrixLoginUrl: (nonce: string): string =>
    `${BASE_URL.replace(/\/api(\/v\d+)?\/?$/, '')}/auth/bitrix/redirect?${new URLSearchParams({ nonce }).toString()}`,
};

export default authApi;
