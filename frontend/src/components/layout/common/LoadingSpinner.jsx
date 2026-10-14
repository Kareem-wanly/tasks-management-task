import './LoadingSpinner.css';

export default function LoadingSpinner({
  size = 'md',
  text = '',
  fullPage = false,
  center = true,
  color = 'primary',
  className = '',
}) {
  const containerClasses = [
    'spinner-container-common',
    fullPage ? 'spinner-fullpage' : '',
    center && !fullPage ? 'spinner-centered' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <div className={containerClasses} role="status" aria-live="polite">
      <div className={`spinner-circle spinner-${size} spinner-color-${color}`} />
      {text && <p className="spinner-text">{text}</p>}
      <span className="sr-only">Loading...</span>
    </div>
  );
}