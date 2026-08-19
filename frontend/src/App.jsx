import { BrowserRouter, Routes, Route } from 'react-router-dom'; 
import AppLayout from './components/layout/AppLayout';
import NotFoundPage from './pages/NotFoundPage';

function DashboardPage() { return <h2>Dashboard Page</h2>; }
function ProjectsPage() { return <h2>Projects Page</h2>; }
function TasksPage() { return <h2>Tasks Page</h2>; }
function UsersPage() { return <h2>Users Page</h2>; }
function RolesPage() { return <h2>Roles Page</h2>; }

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<AppLayout />}>
          <Route index element={<DashboardPage />} />
          <Route path="projects" element={<ProjectsPage />} />
          <Route path="tasks" element={<TasksPage />} />
          <Route path="users" element={<UsersPage />} />
          <Route path="roles" element={<RolesPage />} />
        </Route>
        <Route path="*" element={<NotFoundPage />} /> 
      </Routes>
    </BrowserRouter>
  );
}