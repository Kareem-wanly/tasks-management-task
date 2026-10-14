import { useEffect } from 'react';
import './Notification.css';

export default function Notification({
  type = 'info',
  title = '',
  message = '',
  children,
  onClose = null,
  autoClose = 0,
  icon = null,
  className = '',
}) {
  const normalizedType = type === 'danger' ? 'error' : type;

  useEffect(() => {
    if (autoClose > 0 && typeof onClose === 'function') {
      const timer = setTimeout(() => {
        onClose();
      }, autoClose);
      return () => clearTimeout(timer);
    }
  }, [autoClose, onClose]);

  const getDefaultIcon = () => {
    switch (normalizedType) {
      case 'success':
        return '✓';
      case 'error':
        return '✕';
      case 'warning':
        return '⚠';
      case 'info':
      default:
        return 'ℹ';
    }
  };

  const content = children || message;

  return (
    <div
      className={`notification-common notification-${normalizedType} ${className}`.trim()}
      role="alert"
    >
      <div className="notification-icon" aria-hidden="true">
        {icon || getDefaultIcon()}
      </div>

      <div className="notification-body">
        {title && <h4 className="notification-title">{title}</h4>}
        {content && <div className="notification-message">{content}</div>}
      </div>

      {onClose && (
        <button
          type="button"
          className="notification-close-btn"
          onClick={onClose}
          aria-label="Close notification"
        >
          ×
        </button>
      )}
    </div>
  );
}