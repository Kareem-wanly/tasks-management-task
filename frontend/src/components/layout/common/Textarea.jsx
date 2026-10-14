import './Textarea.css';


export default function Textarea({
  label,
  id,
  name,
  value,
  onChange,
  placeholder = '',
  rows = 4,
  error = null,
  required = false,
  disabled = false,
  helperText = '',
  className = '',
  ...props
}) {
  const textareaId = id || name;
  const errorMessage = Array.isArray(error) ? error[0] : error;

  return (
    <div className={`textarea-group-common ${errorMessage ? 'has-error' : ''} ${className}`.trim()}>
      {label && (
        <label htmlFor={textareaId} className="common-textarea-label">
          {label} {required && <span className="req-star">*</span>}
        </label>
      )}

      <textarea
        id={textareaId}
        name={name}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        rows={rows}
        disabled={disabled}
        required={required}
        className="common-textarea-field"
        {...props}
      />

      {errorMessage && <span className="common-textarea-error">{errorMessage}</span>}
      {!errorMessage && helperText && <span className="common-textarea-helper">{helperText}</span>}
    </div>
  );
}