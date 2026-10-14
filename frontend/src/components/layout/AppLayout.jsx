import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import './AppLayout.css';

export default function AppLayout() {
  const { user, roles, logout, can } = useAuth();

  const canViewUsers = can ? can('users.view') : false;
  const canViewRoles = can ? can('roles.view') : false;
  const showManagementSection = canViewUsers || canViewRoles;

  const getInitials = (name) => {
    if (!name) return 'U';
    return name
      .split(' ')
      .map((n) => n[0])
      .join('')
      .substring(0, 2)
      .toUpperCase();
  };

  return (
    <div className="app-container">
      <aside className="app-sidebar">
        <div className="sidebar-header">
          <div className="sidebar-brand-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
          </div>
          <span className="sidebar-brand-text">Task Manager</span>
        </div>

        <nav className="sidebar-nav">
          <span className="nav-section-label">Overview</span>
          <NavLink to="/" end className={({ isActive }) => `nav-item-link ${isActive ? 'active' : ''}`}>
            <svg className="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span>Dashboard</span>
          </NavLink>

          <NavLink to="/projects" className={({ isActive }) => `nav-item-link ${isActive ? 'active' : ''}`}>
            <svg className="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
            <span>Projects</span>
          </NavLink>

          <NavLink to="/tasks" className={({ isActive }) => `nav-item-link ${isActive ? 'active' : ''}`}>
            <svg className="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Tasks</span>
          </NavLink>

          {showManagementSection && (
            <>
              <span className="nav-section-label" style={{ marginTop: '0.75rem' }}>Management</span>
              
              {canViewUsers && (
                <NavLink to="/users" className={({ isActive }) => `nav-item-link ${isActive ? 'active' : ''}`}>
                  <svg className="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                  </svg>
                  <span>Users</span>
                </NavLink>
              )}

              {canViewRoles && (
                <NavLink to="/roles" className={({ isActive }) => `nav-item-link ${isActive ? 'active' : ''}`}>
                  <svg className="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                  </svg>
                  <span>Roles</span>
                </NavLink>
              )}
            </>
          )}
        </nav>
      </aside>

      <div className="app-main-wrapper">
        <header className="app-topbar">
          <div className="topbar-left">
            <div className="workspace-badge">
              <span className="workspace-dot" />
              <span>Workspace</span>
            </div>
          </div>

          <div className="topbar-right">
            <div className="user-profile-card">
              <div className="user-avatar">{getInitials(user?.name)}</div>
              <div className="user-info-text">
                <span className="user-display-name">{user?.name || 'User'}</span>
                <span className="user-role-title">{roles?.[0] || 'Member'}</span>
              </div>
            </div>

            <button onClick={logout} className="header-logout-btn" title="Sign out">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path strokeLinecap="round" strokeLinejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              <span>Logout</span>
            </button>
          </div>
        </header>

        <main className="app-content-body">
          <Outlet />
        </main>
      </div>
    </div>
  );
}