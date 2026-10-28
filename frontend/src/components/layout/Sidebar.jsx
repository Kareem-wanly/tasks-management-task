import { NavLink } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

export default function Sidebar({ isOpen, onClose }) {
  const { hasRole, can } = useAuth();

  const links = [
    { to: '/', label: 'Dashboard' },
    { to: '/projects', label: 'Projects' },
    { to: '/tasks', label: 'Tasks' },
    { to: '/users', label: 'Users', permission: 'users.view' },
    { to: '/roles', label: 'Roles', permission: 'roles.view' },
  ];

  const visibleLinks = links.filter((link) => {
    if (link.role && (!hasRole || !hasRole(link.role))) {
      return false;
    }
    if (link.permission && (!can || !can(link.permission))) {
      return false;
    }
    return true;
  });

  return (
    <>
      <aside className={`sidebar ${isOpen ? 'open' : ''}`}>
        <div className="sidebar-header">
          Task Manager
        </div>
        <nav className="sidebar-nav">
          {visibleLinks.map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
              onClick={onClose}
            >
              {link.label}
            </NavLink>
          ))}
        </nav>
      </aside>
      <div 
        className={`sidebar-overlay ${isOpen ? 'active' : ''}`} 
        onClick={onClose} 
      />
    </>
  );
}