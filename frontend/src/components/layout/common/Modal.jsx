import { useEffect, useCallback } from 'react';
import './Modal.css';


export default function Modal({
  isOpen,
  onClose,
  title = '',
  children,
  footer = null,
  size = 'md',
  closeOnBackdrop = true,
  closeOnEsc = true,
  className = '',
}) {
  const handleKeyDown = useCallback(
    (e) => {
      if (closeOnEsc && e.key === 'Escape' && typeof onClose === 'function') {
        onClose();
      }
    },
    [closeOnEsc, onClose]
  );

  useEffect(() => {
    if (!isOpen) return;

    document.addEventListener('keydown', handleKeyDown);
    const originalOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = originalOverflow;
    };
  }, [isOpen, handleKeyDown]);

  if (!isOpen) return null;

  const handleBackdropClick = (e) => {
    if (closeOnBackdrop && e.target === e.currentTarget && typeof onClose === 'function') {
      onClose();
    }
  };

  return (
    <div
      className="modal-backdrop-common"
      onClick={handleBackdropClick}
      role="dialog"
      aria-modal="true"
      aria-labelledby={title ? 'common-modal-title' : undefined}
    >
      <div className={`modal-card-common modal-size-${size} ${className}`.trim()}>
        {(title || onClose) && (
          <div className="modal-header-common">
            {title && (
              <h3 id="common-modal-title" className="modal-title-text">
                {title}
              </h3>
            )}
            {onClose && (
              <button
                type="button"
                className="modal-close-btn"
                onClick={onClose}
                aria-label="Close modal"
              >
                ×
              </button>
            )}
          </div>
        )}

        <div className="modal-body-common">{children}</div>

        {footer && <div className="modal-footer-common">{footer}</div>}
      </div>
    </div>
  );
}