import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';


export default function PermissionRoute({ requiredPermission, requiredRole }) {
  const { can, hasRole } = useAuth();

  if (requiredPermission && !can(requiredPermission)) {
    return <Navigate to="/403" replace />;
  }

  if (requiredRole && !hasRole(requiredRole)) {
    return <Navigate to="/403" replace />;
  }

  return <Outlet />;
}