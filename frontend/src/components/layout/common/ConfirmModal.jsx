import Modal from './Modal';
import Button from './Button';
import './ConfirmModal.css';


export default function ConfirmModal({
  isOpen,
  onClose,
  onConfirm,
  title = 'Confirm Action',
  message = 'Are you sure you want to proceed with this action? This cannot be undone.',
  confirmText = 'Confirm',
  cancelText = 'Cancel',
  variant = 'danger',
  loading = false,
  icon = null,
  className = '',
}) {
  const getDefaultIcon = () => {
    switch (variant) {
      case 'danger':
        return '🗑️';
      case 'warning':
        return '⚠️';
      case 'primary':
      default:
        return '❓';
    }
  };

  const footer = (
    <div className="confirm-modal-actions">
      <Button
        variant="secondary"
        size="md"
        onClick={onClose}
        disabled={loading}
      >
        {cancelText}
      </Button>

      <Button
        variant={variant}
        size="md"
        onClick={onConfirm}
        loading={loading}
      >
        {confirmText}
      </Button>
    </div>
  );

  return (
    <Modal
      isOpen={isOpen}
      onClose={loading ? undefined : onClose}
      size="sm"
      footer={footer}
      closeOnBackdrop={!loading}
      closeOnEsc={!loading}
      className={`confirm-modal-wrapper ${className}`.trim()}
    >
      <div className="confirm-modal-body">
        <div className={`confirm-icon-box confirm-icon-${variant}`}>
          {icon || getDefaultIcon()}
        </div>

        <div className="confirm-details">
          <h4 className="confirm-title">{title}</h4>
          <div className="confirm-message">{message}</div>
        </div>
      </div>
    </Modal>
  );
}