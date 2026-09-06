import './Select.css';

export default function Select({
  label,
  id,
  name,
  value,
  onChange,
  options = [],
  placeholder = '',
  error = null,
  required = false,
  disabled = false,
  helperText = '',
  children,
  className = '',
  ...props
}) {
  const selectId = id || name;
  const errorMessage = Array.isArray(error) ? error[0] : error;

  return (
    <div className={`select-group-common ${errorMessage ? 'has-error' : ''} ${className}`.trim()}>
      {label && (
        <label htmlFor={selectId} className="common-select-label">
          {label} {required && <span className="req-star">*</span>}
        </label>
      )}

      <div className="common-select-wrapper">
        <select
          id={selectId}
          name={name}
          value={value}
          onChange={onChange}
          disabled={disabled}
          required={required}
          className="common-select-field"
          {...props}
        >
          {placeholder && (
            <option value="" disabled={required}>
              {placeholder}
            </option>
          )}

          {children
            ? children
            : options.map((opt, idx) => {
                const optValue = typeof opt === 'object' ? (opt.value ?? opt.id) : opt;
                const optLabel = typeof opt === 'object' ? (opt.label ?? opt.name ?? opt.display_name) : opt;
                return (
                  <option key={optValue ?? idx} value={optValue}>
                    {optLabel}
                  </option>
                );
              })}
        </select>
        <span className="common-select-arrow" aria-hidden="true">
          ▼
        </span>
      </div>

      {errorMessage && <span className="common-select-error">{errorMessage}</span>}
      {!errorMessage && helperText && <span className="common-select-helper">{helperText}</span>}
    </div>
  );
}