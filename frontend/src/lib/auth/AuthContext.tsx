import { createContext, useContext, useState, useEffect, useCallback, useMemo, useRef, type ReactNode } from 'react';
import authApi, { type LoginCredentials, type RegisterData } from '@/api/authApi';
import { ApiError } from '@/lib/apiClient';
import { getToken, setToken, clearToken } from '@/lib/tokenStorage';
import { useToast } from '@/lib/toast/ToastContext';
import type { AuthResponse, User } from '@/types/api';

interface AuthContextValue {
  user: User | null;
  /** Role names, e.g. "Administrator". */
  roles: string[];
  permissions: string[];
  isLoading: boolean;
  startupError: string | null;
  isAuthenticated: boolean;
  login: (credentials: LoginCredentials) => Promise<AuthResponse>;
  register: (userData: RegisterData) => Promise<AuthResponse>;
  logout: () => Promise<void>;
  restoreAuthentication: () => Promise<void>;
  /** Reloads the signed-in user (after a profile change) without the full-page loader. */
  refreshUser: () => Promise<void>;
  can: (permissionName: string) => boolean;
  hasRole: (role: string | string[]) => boolean;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [startupError, setStartupError] = useState<string | null>(null);
  const { showToast } = useToast();

  const roles = useMemo(() => user?.roles ?? [], [user]);
  const permissions = useMemo(() => user?.effective_permissions ?? [], [user]);

  // Read inside the 401 listener without re-subscribing on every user change
  const userRef = useRef<User | null>(null);
  useEffect(() => {
    userRef.current = user;
  }, [user]);

  const resetSession = useCallback(() => {
    clearToken();
    setUser(null);
  }, []);

  const restoreAuthentication = useCallback(async () => {
    const token = getToken();

    if (!token) {
      setIsLoading(false);
      return;
    }

    setIsLoading(true);
    setStartupError(null);

    try {
      setUser(await authApi.getCurrentUser());
    } catch (error) {
      console.error('Session restoration failed:', error);
      if (error instanceof ApiError && error.status === 401) {
        resetSession();
      } else {
        setStartupError('Could not connect to the server. Please check your connection.');
      }
    } finally {
      setIsLoading(false);
    }
  }, [resetSession]);

  useEffect(() => {
    restoreAuthentication();
  }, [restoreAuthentication]);

  const refreshUser = useCallback(async () => {
    setUser(await authApi.getCurrentUser());
  }, []);

  useEffect(() => {
    const handleUnauthorized = () => {
      // A failed login also returns 401; only announce an expired session if someone was signed in
      if (userRef.current) {
        showToast('Your session expired. Please sign in again.', 'info');
      }
      resetSession();
    };

    window.addEventListener('auth:unauthorized', handleUnauthorized);
    return () => {
      window.removeEventListener('auth:unauthorized', handleUnauthorized);
    };
  }, [resetSession, showToast]);

  const startSession = (payload: AuthResponse) => {
    setToken(payload.access_token);
    setUser(payload.user);
    setStartupError(null);
  };

  const login = async (credentials: LoginCredentials) => {
    const payload = await authApi.login(credentials);
    startSession(payload);
    return payload;
  };

  const register = async (userData: RegisterData) => {
    const payload = await authApi.register(userData);
    startSession(payload);
    return payload;
  };

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch (error) {
      console.error('Logout error on server:', error);
    } finally {
      resetSession();
    }
  }, [resetSession]);

  const hasRole = useCallback(
    (role: string | string[]) => {
      if (!user) return false;
      const normalizedRoles = roles.map((r) => r.toLowerCase());
      const wanted = Array.isArray(role) ? role : [role];
      return wanted.some((r) => normalizedRoles.includes(r.toLowerCase()));
    },
    [user, roles]
  );

  const can = useCallback(
    (permissionName: string) => {
      if (!user) return false;

      // Administrators can do everything; mirrors the backend Gate::before
      if (roles.includes('Administrator')) return true;

      return permissions.includes(permissionName);
    },
    [user, roles, permissions]
  );

  const value: AuthContextValue = {
    user,
    roles,
    permissions,
    isLoading,
    startupError,
    isAuthenticated: Boolean(user),
    login,
    register,
    logout,
    restoreAuthentication,
    refreshUser,
    can,
    hasRole,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

export default AuthContext;
