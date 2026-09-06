import './ErrorState.css';

/**
 * مكون عرض حالات الخطأ العام (Reusable Error State Component)
 *
 * @param {string} title - عنوان الخطأ الرئيسي
 * @param {string|Error} message - نص تفاصيل الخطأ
 * @param {function} onRetry - دالة إعادة المحاولة (إذا توفرت يظهر زر Try Again)
 * @param {string} retryText - نص زر إعادة المحاولة
 * @param {React.ReactNode|string} icon - أيقونة أو رمز تعبيري معبر
 * @param {React.ReactNode} action - عناصر أو أزرار إضافية مخصصة
 */
export default function ErrorState({
  title = 'Failed to load content',
  message = 'An unexpected error occurred while loading this section. Please try again.',
  onRetry = null,
  retryText = 'Try Again',
  icon = '⚠️',
  action = null,
  className = '',
}) {
  const displayMessage =
    typeof message === 'object' && message !== null
      ? message.message || 'An unexpected error occurred.'
      : message;

  return (
    <div className={`error-state-common ${className}`.trim()} role="alert">
      <div className="error-state-icon" aria-hidden="true">
        {icon}
      </div>
      <h3 className="error-state-title">{title}</h3>
      {displayMessage && <p className="error-state-message">{displayMessage}</p>}

      {(onRetry || action) && (
        <div className="error-state-actions">
          {onRetry && (
            <button type="button" className="btn-error-retry" onClick={onRetry}>
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M23 4v6h-6" />
                <path d="M1 20v-6h6" />
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
              </svg>
              <span>{retryText}</span>
            </button>
          )}
          {action}
        </div>
      )}
    </div>
  );
}