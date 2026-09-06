import './Input.css';

export default function Input({
  label,
  id,
  name,
  type = 'text',
  value,
  onChange,
  placeholder = '',
  error = null,
  required = false,
  disabled = false,
  helperText = '',
  icon = null,
  className = '',
  ...props
}) {
  const inputId = id || name;
  const errorMessage = Array.isArray(error) ? error[0] : error;

  return (
    <div className={`input-group-common ${errorMessage ? 'has-error' : ''} ${className}`.trim()}>
      {label && (
        <label htmlFor={inputId} className="common-input-label">
          {label} {required && <span className="req-star">*</span>}
        </label>
      )}

      <div className="common-input-wrapper">
        {icon && <span className="common-input-icon">{icon}</span>}
        <input
          id={inputId}
          name={name}
          type={type}
          value={value}
          onChange={onChange}
          placeholder={placeholder}
          disabled={disabled}
          required={required}
          className={`common-input-field ${icon ? 'with-icon' : ''}`}
          {...props}
        />
      </div>

      {errorMessage && <span className="common-input-error">{errorMessage}</span>}
      {!errorMessage && helperText && <span className="common-input-helper">{helperText}</span>}
    </div>
  );
}