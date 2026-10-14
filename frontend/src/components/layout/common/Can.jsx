import { useAuth } from '../../../context/AuthContext';

export default function Can({ permission, role, children, fallback = null }) {
  const auth = useAuth();

  const hasPermission = permission
    ? (auth.can ? auth.can(permission) : auth.permissions?.includes(permission))
    : true;

  const hasRequiredRole = role
    ? (auth.hasRole ? auth.hasRole(role) : auth.roles?.includes(role))
    : true;

  if (!hasPermission || !hasRequiredRole) {
    return fallback;
  }

  return children;
}