import { createContext, useContext, useState, useEffect } from 'react';
import authApi from '../api/authApi';

const AuthContext = createContext(null); 

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null); 
  const [roles, setRoles] = useState([]);
  const [permissions, setPermissions] = useState([]);
  const [isLoading, setIsLoading] = useState(true);

  const restoreAuthentication = async () => {
    const token = localStorage.getItem('auth_token');

    if (!token) {
      setIsLoading(false);
      return;
    }

    try {
      const response = await authApi.getCurrentUser();
      const { user: userData, roles: userRoles, permissions: userPermissions } = response.data;

      setUser(userData);
      setRoles(userRoles || []);
      setPermissions(userPermissions || []);
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
    const response = await authApi.login(credentials);
    const { token, user: userData, roles: userRoles, permissions: userPermissions } = response.data;

    if (token) {
      localStorage.setItem('auth_token', token);
    }

    setUser(userData);
    setRoles(userRoles || []);
    setPermissions(userPermissions || []);

    return response.data;
  };

  const register = async (userData) => {
    const response = await authApi.register(userData);
    const { token, user: createdUser, roles: userRoles, permissions: userPermissions } = response.data;

    if (token) {
      localStorage.setItem('auth_token', token);
      setUser(createdUser);
      setRoles(userRoles || []);
      setPermissions(userPermissions || []);
    }

    return response.data;
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


  const can = (permission) => {
    if (!permission) return false;
    if (roles.includes('admin')) return true;
    return permissions.includes(permission);
  };

 
  const hasRole = (role) => {
    if (!role) return false;
    if (Array.isArray(role)) {
      return role.some((r) => roles.includes(r));
    }
    return roles.includes(role);
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