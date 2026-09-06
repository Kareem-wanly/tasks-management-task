import './EmptyState.css';


export default function EmptyState({
  icon = '📭',
  title = 'No data available',
  description = '',
  action = null,
  className = '',
}) {
  return (
    <div className={`empty-state-common ${className}`.trim()}>
      <div className="empty-state-icon" aria-hidden="true">
        {icon}
      </div>
      <h3 className="empty-state-title">{title}</h3>
      {description && <p className="empty-state-description">{description}</p>}
      {action && <div className="empty-state-action">{action}</div>}
    </div>
  );
}