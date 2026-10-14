import './FormError.css';


export default function FormError({
  error,
  title = '',
  onDismiss = null,
  className = '',
}) {
  if (!error) return null;

  const extractErrors = () => {
    if (typeof error === 'string') return [error];
    if (Array.isArray(error)) return error;
    if (typeof error === 'object') {
      return Object.values(error).flat();
    }
    return ['An unexpected error occurred.'];
  };

  const errorList = extractErrors();

  if (errorList.length === 0) return null;

  return (
    <div className={`form-error-alert ${className}`.trim()} role="alert">
      <div className="form-error-icon" aria-hidden="true">
        ⚠️
      </div>

      <div className="form-error-content">
        {title && <h4 className="form-error-title">{title}</h4>}

        {errorList.length === 1 ? (
          <p className="form-error-message">{errorList[0]}</p>
        ) : (
          <ul className="form-error-list">
            {errorList.map((msg, index) => (
              <li key={index}>{msg}</li>
            ))}
          </ul>
        )}
      </div>

      {onDismiss && (
        <button
          type="button"
          className="form-error-dismiss"
          onClick={onDismiss}
          aria-label="Dismiss error"
        >
          ×
        </button>
      )}
    </div>
  );
}