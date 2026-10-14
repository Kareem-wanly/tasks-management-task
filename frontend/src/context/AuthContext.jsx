import { createContext, useContext, useState, useEffect } from 'react';
import authApi from '../api/authApi';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  const extractUserMeta = (userData) => {
    let userRoles = [];
    let userPermissions = [];

    if (userData?.roles && Array.isArray(userData.roles)) {
      userRoles = userData.roles.map((r) => (typeof r === 'object' ? r.name : r));

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

    if (userData?.permissions && Array.isArray(userData.permissions)) {
      userData.permissions.forEach((p) => {
        const permName = typeof p === 'object' ? p.name : p;
        if (permName && !userPermissions.includes(permName)) {
          userPermissions.push(permName);
        }
      });
    }

    return { userRoles, userPermissions };
  };

  const restoreAuthentication = async () => {
    const token = localStorage.getItem('auth_token');

    if (!token) {
      setIsLoading(false);
      return;
    }

    try {
      const res = await authApi.getCurrentUser();
      const payload = res.data || res;
      const currentUser = payload.user || payload;
      const { userRoles, userPermissions } = extractUserMeta(currentUser);

      setUser(currentUser);
      setRoles(userRoles);
      setPermissions(userPermissions);
    } catch (error) {
      console.error('Session restoration failed:', error);
      localStorage.removeItem('auth_token');
      setUser(null);
      setRoles([]);
      setPermissions([]);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    restoreAuthentication();
  }, []);

  const login = async (credentials) => {
    const res = await authApi.login(credentials);
    const payload = res.data || res;

    const token = payload.access_token || payload.token;
    const currentUser = payload.user || payload;

    if (token) {
      localStorage.setItem('auth_token', token);
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
    const currentUser = payload.user || payload;

    if (token) {
      localStorage.setItem('auth_token', token);
    }

    const { userRoles, userPermissions } = extractUserMeta(currentUser);

    setUser(currentUser);
    setRoles(userRoles);
    setPermissions(userPermissions);

    return payload;
  };

  const logout = async () => {
    try {
      await authApi.logout();
    } catch (error) {
      console.error('Logout error on server:', error);
    } finally {
      localStorage.removeItem('auth_token');
      setUser(null);
      setRoles([]);
      setPermissions([]);
    }
  };

  const hasRole = (role) => {
    if (!role) return false;
    const normalizedRoles = roles.map((r) => String(r).toLowerCase());
    if (Array.isArray(role)) {
      return role.some((r) => normalizedRoles.includes(String(r).toLowerCase()));
    }
    return normalizedRoles.includes(String(role).toLowerCase());
  };

  const can = (permissionName) => {
  if (!user) return false;

  const roles = user.roles || [];
  const isAdmin = roles.some((r) => 
    typeof r === 'string' ? r === 'Administrator' : r.name === 'Administrator'
  );
  if (isAdmin) return true;

  const permissions = user.effective_permissions || user.permissions || [];

  if (Array.isArray(permissions)) {
    return permissions.some((p) => 
      typeof p === 'string' ? p === permissionName : p.name === permissionName
    );
  }

  return false;
};

  const value = {
    user,
    setUser,
    roles,
    setRoles,
    permissions,
    setPermissions,
    isLoading,
    setIsLoading,
    isAuthenticated: !!user,
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