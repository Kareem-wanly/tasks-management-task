import { Routes, Route, Navigate } from 'react-router-dom';
import ProtectedRoute from './ProtectedRoute';
import PermissionRoute from './PermissionRoute';

import AppLayout from '../components/layout/AppLayout';

import LoginPage from '../pages/auth/LoginPage';
import RegisterPage from '../pages/auth/RegisterPage';
import ForbiddenPage from '../pages/errors/ForbiddenPage';
import NotFoundPage from '../pages/errors/NotFoundPage';
import DashboardPage from '../pages/dashboard/DashboardPage';

function ProjectsPage() { return <h2>Projects Page</h2>; }
function TasksPage() { return <h2>Tasks Page</h2>; }
function UsersPage() { return <h2>Users Page</h2>; }
function RolesPage() { return <h2>Roles Page</h2>; }

export default function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/403" element={<ForbiddenPage />} />

      <Route element={<ProtectedRoute />}>
        <Route path="/" element={<AppLayout />}>
          <Route index element={<DashboardPage />} />
          <Route path="projects" element={<ProjectsPage />} />
          <Route path="tasks" element={<TasksPage />} />

          <Route element={<PermissionRoute requiredPermission="users.view" />}>
            <Route path="users" element={<UsersPage />} />
          </Route>

          <Route element={<PermissionRoute requiredPermission="roles.view" />}>
            <Route path="roles" element={<RolesPage />} />
          </Route>
        </Route>
      </Route>

      <Route path="/404" element={<NotFoundPage />} />
      <Route path="*" element={<Navigate to="/404" replace />} />
    </Routes>
  );
}