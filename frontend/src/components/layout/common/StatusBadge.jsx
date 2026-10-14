import './StatusBadge.css';


export default function StatusBadge({
  status = '',
  label = '',
  variant,
  dot = true,
  size = 'md',
  className = '',
}) {
  const normalizedStatus = String(status).toLowerCase().trim();

  const resolveVariant = () => {
    if (variant) return variant;

    switch (normalizedStatus) {
      case 'active':
      case 'completed':
      case 'published':
      case 'approved':
      case 'success':
        return 'success';

      case 'pending':
      case 'in_progress':
      case 'in progress':
      case 'review':
      case 'warning':
        return 'warning';

      case 'inactive':
      case 'cancelled':
      case 'rejected':
      case 'failed':
      case 'blocked':
      case 'danger':
        return 'danger';

      case 'draft':
      case 'archived':
      case 'info':
        return 'info';

      default:
        return 'neutral';
    }
  };

  const badgeVariant = resolveVariant();

  const formatStatus = (str) => {
    if (!str) return '';
    return str
      .replace(/[_-]/g, ' ')
      .replace(/\b\w/g, (char) => char.toUpperCase());
  };

  const displayText = label || formatStatus(status);

  return (
    <span className={`status-badge-common badge-${badgeVariant} badge-size-${size} ${className}`.trim()}>
      {dot && <span className="status-badge-dot" aria-hidden="true" />}
      <span className="status-badge-text">{displayText}</span>
    </span>
  );
}