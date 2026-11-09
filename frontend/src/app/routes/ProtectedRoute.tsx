import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '@/lib/auth/AuthContext';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ErrorState from '@/components/ui/ErrorState';

interface ProtectedRouteProps {
  permission?: string;
  role?: string;
}

export default function ProtectedRoute({ permission, role }: ProtectedRouteProps) {
  const { isAuthenticated, isLoading, startupError, restoreAuthentication, can, hasRole } = useAuth();
  const location = useLocation();

  if (isLoading) {
    return <LoadingSpinner fullPage text="Loading..." />;
  }

  // The token is still valid but /me failed (network or 5xx): don't send the user to /login
  if (!isAuthenticated && startupError) {
    return (
      <ErrorState
        title="Could not reach the server"
        message={startupError}
        onRetry={restoreAuthentication}
      />
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  if (permission && !can(permission)) {
    return <Navigate to="/403" replace />;
  }

  if (role && !hasRole(role)) {
    return <Navigate to="/403" replace />;
  }

  return <Outlet />;
}
