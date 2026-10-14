import './PriorityBadge.css';


export default function PriorityBadge({
  priority = 'medium',
  label = '',
  showIcon = true,
  size = 'md',
  className = '',
}) {
  const normalizedPriority = String(priority).toLowerCase().trim();

  const getPriorityConfig = () => {
    switch (normalizedPriority) {
      case 'urgent':
      case 'critical':
        return {
          variant: 'urgent',
          icon: '⚡',
          defaultLabel: 'Urgent',
        };
      case 'high':
        return {
          variant: 'high',
          icon: '▲',
          defaultLabel: 'High',
        };
      case 'medium':
      case 'normal':
        return {
          variant: 'medium',
          icon: '●',
          defaultLabel: 'Medium',
        };
      case 'low':
        return {
          variant: 'low',
          icon: '▼',
          defaultLabel: 'Low',
        };
      default:
        return {
          variant: 'neutral',
          icon: '•',
          defaultLabel: priority || 'Normal',
        };
    }
  };

  const config = getPriorityConfig();
  const displayText = label || config.defaultLabel;

  return (
    <span
      className={`priority-badge-common priority-${config.variant} priority-size-${size} ${className}`.trim()}
    >
      {showIcon && <span className="priority-badge-icon" aria-hidden="true">{config.icon}</span>}
      <span className="priority-badge-text">{displayText}</span>
    </span>
  );
}