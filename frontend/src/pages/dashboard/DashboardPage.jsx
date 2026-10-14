import { useAuth } from '../../context/AuthContext';
import './DashboardPage.css';

export default function DashboardPage() {
  const { user, roles } = useAuth();

  return (
    <div className="dashboard-container">
      <div className="welcome-card">
        <div className="welcome-icon-box">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
        </div>

        <h1 className="welcome-title">Welcome, {user?.name || 'User'}!</h1>
        
        <p className="welcome-subtitle">
          This is your workspace overview. Project statistics, assigned tasks, and completion metrics will appear here once connected.
        </p>

        {roles?.length > 0 && (
          <div className="user-roles-container">
            {roles.map((role, index) => (
              <span key={index} className="role-tag">
                {role}
              </span>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}