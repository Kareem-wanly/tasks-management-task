import { useState, useEffect } from 'react';
import usersApi from '../../api/usersApi';
import { useToast } from '../../context/ToastContext'; 
import './UserModal.css';

export default function UserModal({ isOpen, userToEdit, roles = [], onClose, onSuccess }) {
  const { showToast } = useToast(); 
  const isEditing = Boolean(userToEdit);

  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: '',
    is_active: true,
  });

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [validationErrors, setValidationErrors] = useState({});

  useEffect(() => {
    if (userToEdit) {
      setFormData({
        name: userToEdit.name || '',
        email: userToEdit.email || '',
        password: '',
        password_confirmation: '',
        role_id: userToEdit.role_id || userToEdit.role?.id || '',
        is_active: userToEdit.is_active ?? true,
      });
    } else {
      setFormData({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role_id: roles[0]?.id || '',
        is_active: true,
      });
    }
    setError(null);
    setValidationErrors({});
  }, [userToEdit, roles, isOpen]);

  if (!isOpen) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setValidationErrors({});

    const payload = {
      name: formData.name,
      email: formData.email,
      role_id: formData.role_id ? Number(formData.role_id) : null,
      is_active: formData.is_active,
    };

    if (!isEditing || formData.password) {
      payload.password = formData.password;
      payload.password_confirmation = formData.password_confirmation;
    }

    try {
      if (isEditing) {
        await usersApi.update(userToEdit.id, payload);
        showToast('User updated successfully!', 'success'); 
      } else {
        await usersApi.create(payload);
        showToast('User created successfully!', 'success'); 
      }
      onSuccess();
    } catch (err) {
      console.error('Failed to save user:', err);
      if (err.data?.errors) {
        setValidationErrors(err.data.errors);
      } else {
        const msg = err.data?.message || 'Failed to save user details.';
        setError(msg);
        showToast(msg, 'error'); 
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="modal-backdrop">
      <div className="modal-card">
        <div className="modal-header">
          <h2>{isEditing ? 'Edit User' : 'Create New User'}</h2>
          <button className="btn-close-modal" onClick={onClose} type="button">
            &times;
          </button>
        </div>

        {error && <div className="alert-modal danger">{error}</div>}

        <form onSubmit={handleSubmit} className="modal-form">
          <div className="form-group">
            <label>Full Name <span className="req">*</span></label>
            <input
              type="text"
              placeholder="e.g. John Doe"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              className={validationErrors.name ? 'input-error' : ''}
              required
            />
            {validationErrors.name && (
              <span className="error-text">{validationErrors.name[0]}</span>
            )}
          </div>

          <div className="form-group">
            <label>Email Address <span className="req">*</span></label>
            <input
              type="email"
              placeholder="e.g. john@example.com"
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              className={validationErrors.email ? 'input-error' : ''}
              required
            />
            {validationErrors.email && (
              <span className="error-text">{validationErrors.email[0]}</span>
            )}
          </div>

          <div className="form-group">
            <label>System Role <span className="req">*</span></label>
            <select
              value={formData.role_id}
              onChange={(e) => setFormData({ ...formData, role_id: e.target.value })}
              className={validationErrors.role_id ? 'input-error' : ''}
              required
            >
              <option value="">Select a role...</option>
              {roles.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.display_name || r.name}
                </option>
              ))}
            </select>
            {validationErrors.role_id && (
              <span className="error-text">{validationErrors.role_id[0]}</span>
            )}
          </div>

          <div className="form-row">
            <div className="form-group">
              <label>
                {isEditing ? 'New Password (Optional)' : 'Password'} {!isEditing && <span className="req">*</span>}
              </label>
              <input
                type="password"
                placeholder={isEditing ? 'Leave blank to keep current' : 'Enter password'}
                value={formData.password}
                onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                className={validationErrors.password ? 'input-error' : ''}
                required={!isEditing}
              />
              {validationErrors.password && (
                <span className="error-text">{validationErrors.password[0]}</span>
              )}
            </div>

            <div className="form-group">
              <label>
                Confirm Password {!isEditing && <span className="req">*</span>}
              </label>
              <input
                type="password"
                placeholder="Confirm password"
                value={formData.password_confirmation}
                onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                required={!isEditing || Boolean(formData.password)}
              />
            </div>
          </div>

          <div className="form-group-checkbox">
            <label>
              <input
                type="checkbox"
                checked={formData.is_active}
                onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
              />
              <span>Account is Active</span>
            </label>
          </div>

          <div className="modal-actions">
            <button
              type="button"
              className="btn-modal-cancel"
              onClick={onClose}
              disabled={saving}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="btn-modal-submit"
              disabled={saving}
            >
              {saving ? 'Saving...' : isEditing ? 'Update User' : 'Create User'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}