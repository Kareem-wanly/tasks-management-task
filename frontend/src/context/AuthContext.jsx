import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import authApi from '../api/authApi';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  const extractUserMeta = useCallback((userData) => {
    let userRoles = [];
    let userPermissions = [];

    if (!userData) return { userRoles, userPermissions };

    if (userData.role) {
      const singleRole = typeof userData.role === 'object' ? userData.role.name : userData.role;
      if (singleRole) userRoles.push(singleRole);
    }
    if (Array.isArray(userData.roles)) {
      userData.roles.forEach((r) => {
        const rName = typeof r === 'object' ? r.name : r;
        if (rName && !userRoles.includes(rName)) {
          userRoles.push(rName);
        }
      });
    }

    if (Array.isArray(userData.roles)) {
      userData.roles.forEach((role) => {
        if (role && typeof role === 'object' && Array.isArray(role.permissions)) {
          role.permissions.forEach((p) => {
            const permName = typeof p === 'object' ? p.name : p;
            if (permName && !userPermissions.includes(permName)) {
              userPermissions.push(permName);
            }
          });
        }
      });
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

  const restoreAuthentication = useCallback(async () => {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');

    if (!token) {
      setIsLoading(false);
      return;
    }

    try {
      const res = await authApi.getCurrentUser ? await authApi.getCurrentUser() : await authApi.me();
      const payload = res.data || res;
      const currentUser = payload.user || payload.data || payload;
      const { userRoles, userPermissions } = extractUserMeta(currentUser);

      setUser(currentUser);
      setRoles(userRoles);
      setPermissions(userPermissions);
    } catch (error) {
      console.error('Session restoration failed:', error);
      localStorage.removeItem('auth_token');
      localStorage.removeItem('token');
      setUser(null);
      setRoles([]);
      setPermissions([]);
    } finally {
      setIsLoading(false);
    }
  }, [extractUserMeta]);

  useEffect(() => {
    restoreAuthentication();
  }, [restoreAuthentication]);

  const login = async (credentials) => {
    const res = await authApi.login(credentials);
    const payload = res.data || res;

    const token = payload.access_token || payload.token;
    const currentUser = payload.user || payload.data || payload;

    if (token) {
      localStorage.setItem('auth_token', token);
      localStorage.setItem('token', token);
    }

    const { userRoles, userPermissions } = extractUserMeta(currentUser);

    setUser(currentUser);
    setRoles(userRoles);
    setPermissions(userPermissions);

    return payload;
  };

  const register = async (userData) => {
    const res = await authApi.register(userData);
    const payload = res.data || res;

    const token = payload.access_token || payload.token;
    const currentUser = payload.user || payload.data || payload;

    if (token) {
      localStorage.setItem('auth_token', token);
      localStorage.setItem('token', token);
    }

    const { userRoles, userPermissions } = extractUserMeta(currentUser);

    setUser(currentUser);
    setRoles(userRoles);
    setPermissions(userPermissions);

    return payload;
  };

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch (error) {
      console.error('Logout error on server:', error);
    } finally {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('token');
      setUser(null);
      setRoles([]);
      setPermissions([]);
    }
  }, []);

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

      const isAdmin =
        user.is_admin ||
        roles.some((r) => ['administrator', 'admin'].includes(String(r).toLowerCase())) ||
        (user.role && ['administrator', 'admin'].includes(String(typeof user.role === 'object' ? user.role.name : user.role).toLowerCase()));

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