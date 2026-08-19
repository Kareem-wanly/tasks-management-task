import { Link } from 'react-router-dom';

export default function NotFoundPage() {
  return (
    <div style={{ textAlign: 'center', padding: '5rem 1rem' }}>
      <h1 style={{ fontSize: '4.5rem', color: 'var(--primary-color)', marginBottom: '0.5rem' }}>404</h1>
      <h2 style={{ marginBottom: '1rem', color: 'var(--text-main)' }}>Page Not Found</h2>
      <p style={{ color: 'var(--text-muted)', marginBottom: '2rem' }}>
        The page you are looking for does not exist or has been moved.
      </p>
      <Link 
        to="/" 
        style={{
          display: 'inline-block',
          padding: '0.75rem 1.5rem',
          backgroundColor: 'var(--primary-color)',
          color: '#ffffff',
          borderRadius: 'var(--radius-sm)',
          fontWeight: '500'
        }}
      >
        Return to Dashboard
      </Link>
    </div>
  );
}