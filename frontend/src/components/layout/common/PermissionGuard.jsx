
export default function PermissionGuard({
  permission,
  role,
  requireAll = false,
  fallback = null,
  children,
}) {
  const getUserData = () => {
    try {
      const stored = localStorage.getItem('user') || localStorage.getItem('auth_user');
      return stored ? JSON.parse(stored) : null;
    } catch {
      return null;
    }
  };

  const user = getUserData();

  if (!user) return fallback;

  const userPermissions = Array.isArray(user.permissions)
    ? user.permissions.map((p) => (typeof p === 'string' ? p : p.name))
    : [];

  const userRoles = Array.isArray(user.roles)
    ? user.roles.map((r) => (typeof r === 'string' ? r : r.name || r.slug))
    : user.role
    ? [typeof user.role === 'string' ? user.role : user.role.name || user.role.slug]
    : [];

  if (userRoles.includes('admin') || userRoles.includes('super-admin')) {
    return children;
  }

  if (role) {
    const requiredRoles = Array.isArray(role) ? role : [role];
    const hasRole = requireAll
      ? requiredRoles.every((r) => userRoles.includes(r))
      : requiredRoles.some((r) => userRoles.includes(r));

    if (!hasRole) return fallback;
  }

  if (permission) {
    const requiredPerms = Array.isArray(permission) ? permission : [permission];
    const hasPermission = requireAll
      ? requiredPerms.every((p) => userPermissions.includes(p))
      : requiredPerms.some((p) => userPermissions.includes(p));

    if (!hasPermission) return fallback;
  }

  return children;
}