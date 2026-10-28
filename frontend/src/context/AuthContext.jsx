import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import authApi from '../api/authApi';
import { getToken, setToken, clearToken } from '../api/tokenStorage';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [startupError, setStartupError] = useState(null);

  const extractUserMeta = useCallback((userData) => {
    let userRoles = [];
    let userPermissions = [];

    if (!userData) return { userRoles, userPermissions };

    if (Array.isArray(userData.roles)) {
      userData.roles.forEach((r) => {
        const rName = typeof r === 'object' ? r.name : r;
        if (rName && !userRoles.includes(rName)) {
          userRoles.push(rName);
        }
      });
    } else if (userData.role) {
      const singleRole = typeof userData.role === 'object' ? userData.role.name : userData.role;
      if (singleRole) userRoles.push(singleRole);
    }

    const directPerms = userData.effective_permissions || userData.permissions;
    if (Array.isArray(directPerms)) {
      directPerms.forEach((p) => {
        const permName = typeof p === 'object' ? p.name : p;
        if (permName && !userPermissions.includes(permName)) {
          userPermissions.push(permName);
        }
      });
    }

    return { userRoles, userPermissions };
  }, []);

  const resetSession = useCallback(() => {
    clearToken();
    setUser(null);
    setRoles([]);
    setPermissions([]);
  }, []);

  const restoreAuthentication = useCallback(async () => {
    const token = getToken();

    if (!token) {
      setIsLoading(false);
      return;
    }

    try {
      setStartupError(null);
      const res = await authApi.getCurrentUser();
      const currentUser = res?.user || res?.data || res;
      const { userRoles, userPermissions } = extractUserMeta(currentUser);

      setUser(currentUser);
      setRoles(userRoles);
      setPermissions(userPermissions);
    } catch (error) {
      console.error('Session restoration failed:', error);
      if (error?.status === 401) {
        resetSession();
      } else {
        setStartupError('Could not connect to the server. Please check your connection.');
      }
    } finally {
      setIsLoading(false);
    }
  }, [extractUserMeta, resetSession]);

  useEffect(() => {
    restoreAuthentication();
  }, [restoreAuthentication]);

  useEffect(() => {
    const handleUnauthorized = () => {
      resetSession();
    };

    window.addEventListener('auth:unauthorized', handleUnauthorized);
    return () => {
      window.removeEventListener('auth:unauthorized', handleUnauthorized);
    };
  }, [resetSession]);

  const login = async (credentials) => {
    const res = await authApi.login(credentials);
    const payload = res?.data || res;

    const token = payload.access_token || payload.token;
    const currentUser = payload.user || payload.data || payload;

    if (token) {
      setToken(token);
    }

    const { userRoles, userPermissions } = extractUserMeta(currentUser);

    setUser(currentUser);
    setRoles(userRoles);
    setPermissions(userPermissions);
    setStartupError(null);

    return payload;
  };

  const register = async (userData) => {
    const res = await authApi.register(userData);
    const payload = res?.data || res;

    const token = payload.access_token || payload.token;
    const currentUser = payload.user || payload.data || payload;

    if (token) {
      setToken(token);
    }

    const { userRoles, userPermissions } = extractUserMeta(currentUser);

    setUser(currentUser);
    setRoles(userRoles);
    setPermissions(userPermissions);
    setStartupError(null);

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
    (role) => {
      if (!user || !role) return false;
      const normalizedRoles = roles.map((r) => String(r).toLowerCase());
      if (Array.isArray(role)) {
        return role.some((r) => normalizedRoles.includes(String(r).toLowerCase()));
      }
      return normalizedRoles.includes(String(role).toLowerCase());
    },
    [user, roles]
  );

  const can = useCallback(
    (permissionName) => {
      if (!user) return false;

      // مطابقة دور المدير العام
      const isAdmin = roles.some((r) => ['administrator', 'admin'].includes(String(r).toLowerCase()));
      if (isAdmin) return true;

      return permissions.includes(permissionName);
    },
    [user, roles, permissions]
  );

  const value = {
    user,
    setUser,
    roles,
    setRoles,
    permissions,
    setPermissions,
    loading: isLoading,
    isLoading,
    startupError,
    isAuthenticated: Boolean(user),
    login,
    register,
    logout,
    restoreAuthentication,
    can,
    hasRole,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

export default AuthContext;