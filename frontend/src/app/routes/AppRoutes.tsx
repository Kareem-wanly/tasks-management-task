import { lazy, Suspense } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import ProtectedRoute from './ProtectedRoute';

import AppShell from '../shell/AppShell';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { LoginPage, RegisterPage } from '@/features/auth';
import ForbiddenPage from '../pages/ForbiddenPage';
import NotFoundPage from '../pages/NotFoundPage';

// Pages behind login load on demand, so the login screen doesn't download the whole app
const ProfilePage = lazy(() => import('@/features/profile').then((m) => ({ default: m.ProfilePage })));
const DashboardPage = lazy(() => import('@/features/dashboard').then((m) => ({ default: m.DashboardPage })));
const ProjectsPage = lazy(() => import('@/features/projects').then((m) => ({ default: m.ProjectsPage })));
const ProjectDetailsPage = lazy(() => import('@/features/projects').then((m) => ({ default: m.ProjectDetailsPage })));
const TasksPage = lazy(() => import('@/features/tasks').then((m) => ({ default: m.TasksPage })));
const TaskDetailsPage = lazy(() => import('@/features/tasks').then((m) => ({ default: m.TaskDetailsPage })));
const UsersPage = lazy(() => import('@/features/users').then((m) => ({ default: m.UsersPage })));
const RolesPage = lazy(() => import('@/features/roles').then((m) => ({ default: m.RolesPage })));
const RoleFormPage = lazy(() => import('@/features/roles').then((m) => ({ default: m.RoleFormPage })));

export default function AppRoutes() {
  return (
    <Suspense fallback={<LoadingSpinner fullPage text="Loading..." />}>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/403" element={<ForbiddenPage />} />
        <Route path="/unauthorized" element={<Navigate to="/403" replace />} />

        <Route element={<ProtectedRoute />}>
          <Route path="/" element={<AppShell />}>
            <Route index element={<DashboardPage />} />

            <Route path="projects" element={<ProjectsPage />} />
            <Route path="projects/:id" element={<ProjectDetailsPage />} />

            <Route path="tasks" element={<TasksPage />} />
            <Route path="tasks/:id" element={<TaskDetailsPage />} />
            <Route path="profile" element={<ProfilePage />} />

            <Route element={<ProtectedRoute permission="users.view" />}>
              <Route path="users" element={<UsersPage />} />
            </Route>

            <Route element={<ProtectedRoute permission="roles.view" />}>
              <Route path="roles" element={<RolesPage />} />
            </Route>
            <Route element={<ProtectedRoute permission="roles.create" />}>
              <Route path="roles/create" element={<RoleFormPage />} />
            </Route>
            <Route element={<ProtectedRoute permission="roles.update" />}>
              <Route path="roles/:id" element={<RoleFormPage />} />
            </Route>
          </Route>
        </Route>

        <Route path="/404" element={<NotFoundPage />} />
        <Route path="*" element={<Navigate to="/404" replace />} />
      </Routes>
    </Suspense>
  );
}