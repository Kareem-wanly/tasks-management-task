import { useAuth } from '../../context/AuthContext';


export default function Can({ permission, role, children, fallback = null }) {
  const { can, hasRole } = useAuth();

  let hasPermissionAccess = true;
  let hasRoleAccess = true;

  if (permission) {
    hasPermissionAccess = can(permission);
  }

  if (role) {
    hasRoleAccess = hasRole(role);
  }

  if (hasPermissionAccess && hasRoleAccess) {
    return <>{children}</>;
  }

  return fallback;
}