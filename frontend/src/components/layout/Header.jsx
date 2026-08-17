export default function Header({ onToggleSidebar }) {
  return (
    <header className="header">
      <button 
        type="button" 
        className="mobile-menu-btn" 
        onClick={onToggleSidebar}
        aria-label="Toggle Menu"
      >
        ☰
      </button>
      <div className="header-title">
        <span>Workspace</span>
      </div>
      <div className="header-user">
        <span>User Profile</span>
      </div>
    </header>
  );
}