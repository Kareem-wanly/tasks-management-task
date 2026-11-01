import { useState, useEffect } from 'react';
import usersApi from '../../api/usersApi';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext'; 
import './UserModal.css';

export default function UserModal({ isOpen, userToEdit, roles = [], onClose, onSuccess }) {
  const { can } = useAuth() || {};
  const { showToast } = useToast(); 
  const isEditing = Boolean(userToEdit);

  const canAssignRole = typeof can === 'function' ? can('roles.assign') : false;

  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: '',
  });

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [validationErrors, setValidationErrors] = useState({});

  useEffect(() => {
    setFormData({
      name: userToEdit?.name || '',
      email: userToEdit?.email || '',
      password: '',
      password_confirmation: '',
      role_id: '',
    });
    setError(null);
    setValidationErrors({});
  }, [userToEdit, isOpen]);

  if (!isOpen) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setValidationErrors({});

    // UserController::update only accepts name and email; roles go through AssignRoleModal
    const payload = {
      name: formData.name,
      email: formData.email,
    };

    if (!isEditing) {
      if (formData.password !== formData.password_confirmation) {
        setValidationErrors({ password: ['The password confirmation does not match.'] });
        setSaving(false);
        return;
      }
      payload.password = formData.password;
      if (canAssignRole && formData.role_id) {
        payload.role_id = Number(formData.role_id);
      }
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
              disabled={saving}
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
              disabled={saving}
            />
            {validationErrors.email && (
              <span className="error-text">{validationErrors.email[0]}</span>
            )}
          </div>

          {!isEditing && (
            <>
              <div className="form-group">
                <label>System Role</label>
                <select
                  value={formData.role_id}
                  onChange={(e) => setFormData({ ...formData, role_id: e.target.value })}
                  className={validationErrors.role_id ? 'input-error' : ''}
                  disabled={saving || !canAssignRole}
                >
                  <option value="">Member (default)</option>
                  {roles.map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.display_name || r.name}
                    </option>
                  ))}
                </select>
                {!canAssignRole && (
                  <small style={{ color: '#64748b', fontSize: '0.75rem', marginTop: '4px', display: 'block' }}>
                    🔒 Requires <code>roles.assign</code> permission to change roles.
                  </small>
                )}
                {validationErrors.role_id && (
                  <span className="error-text">{validationErrors.role_id[0]}</span>
                )}
              </div>

              <div className="form-row">
                <div className="form-group">
                  <label>
                    Password <span className="req">*</span>
                  </label>
                  <input
                    type="password"
                    placeholder="At least 8 characters"
                    value={formData.password}
                    onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                    className={validationErrors.password ? 'input-error' : ''}
                    required
                    minLength={8}
                    disabled={saving}
                  />
                  {validationErrors.password && (
                    <span className="error-text">{validationErrors.password[0]}</span>
                  )}
                </div>

                <div className="form-group">
                  <label>
                    Confirm Password <span className="req">*</span>
                  </label>
                  <input
                    type="password"
                    placeholder="Confirm password"
                    value={formData.password_confirmation}
                    onChange={(e) => setFormData({ ...formData, password_confirmation: e.target.value })}
                    required
                    disabled={saving}
                  />
                </div>
              </div>
            </>
          )}

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