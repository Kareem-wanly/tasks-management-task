import { useState, useEffect, useRef } from 'react';
import './SearchInput.css';


export default function SearchInput({
  value: controlledValue,
  onChange,
  onSearch,
  debounceMs = 350,
  placeholder = 'Search...',
  onClear = null,
  disabled = false,
  className = '',
  ...props
}) {
  const [internalValue, setInternalValue] = useState(controlledValue || '');
  const isFirstMount = useRef(true);

  useEffect(() => {
    if (controlledValue !== undefined) {
      setInternalValue(controlledValue);
    }
  }, [controlledValue]);

  useEffect(() => {
    if (isFirstMount.current) {
      isFirstMount.current = false;
      return;
    }

    if (typeof onSearch !== 'function') return;

    const timer = setTimeout(() => {
      onSearch(internalValue);
    }, debounceMs);

    return () => clearTimeout(timer);
  }, [internalValue, debounceMs, onSearch]);

  const handleChange = (e) => {
    const val = e.target.value;
    setInternalValue(val);
    if (typeof onChange === 'function') {
      onChange(e);
    }
  };

  const handleClear = () => {
    setInternalValue('');
    if (typeof onChange === 'function') {
      onChange({ target: { value: '' } });
    }
    if (typeof onSearch === 'function') {
      onSearch('');
    }
    if (typeof onClear === 'function') {
      onClear();
    }
  };

  return (
    <div className={`search-input-wrapper-common ${className}`.trim()}>
      <span className="search-icon" aria-hidden="true">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
      </span>

      <input
        type="text"
        value={internalValue}
        onChange={handleChange}
        placeholder={placeholder}
        disabled={disabled}
        className="search-field"
        {...props}
      />

      {internalValue && !disabled && (
        <button
          type="button"
          onClick={handleClear}
          className="search-clear-btn"
          aria-label="Clear search"
        >
          ×
        </button>
      )}
    </div>
  );
}