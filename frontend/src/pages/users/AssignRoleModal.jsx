import { useState, useEffect } from 'react';
import usersApi from '../../api/usersApi';
import rolesApi from '../../api/rolesApi';

export default function AssignRoleModal({ isOpen, onClose, onSuccess, user }) {
  const [roles, setRoles] = useState([]);
  const [selectedRoleIds, setSelectedRoleIds] = useState([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!isOpen || !user) return;

    const currentRoleIds = user.roles ? user.roles.map((r) => r.id) : [];
    setSelectedRoleIds(currentRoleIds);
    setError(null);
    setLoading(true);

    rolesApi
  .getAll()
  .then((res) => {
    const list = res.data?.roles || res.data?.data || res.data || [];
    setRoles(Array.isArray(list) ? list : []);
  })
  .catch((err) => {
    console.error('Failed to load roles:', err);
    setError('Failed to load available roles.');
  })
  .finally(() => setLoading(false));
  }, [isOpen, user]);

  if (!isOpen || !user) return null;

  const handleToggleRole = (roleId) => {
    setSelectedRoleIds((prev) =>
      prev.includes(roleId) ? prev.filter((id) => id !== roleId) : [...prev, roleId]
    );
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      await usersApi.syncRoles(user.id, selectedRoleIds);
      onSuccess();
    } catch (err) {
      console.error('Failed to update roles:', err);
      setError(err.data?.message || 'Failed to update user roles.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '480px' }}>
        <div className="modal-header">
          <h3>Manage Roles for {user.name}</h3>
          <button className="modal-close-btn" onClick={onClose} disabled={submitting}>
            ✕
          </button>
        </div>

        {error && <div className="alert alert-danger" style={{ marginBottom: '14px' }}>{error}</div>}

        {loading ? (
          <div style={{ padding: '24px', textAlign: 'center', color: '#64748b' }}>
            Loading roles...
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <p style={{ fontSize: '0.875rem', color: '#64748b', marginBottom: '16px' }}>
              Assign or revoke roles for <strong>{user.email}</strong>:
            </p>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginBottom: '24px' }}>
              {roles.map((role) => {
                const isChecked = selectedRoleIds.includes(role.id);
                return (
                  <label
                    key={role.id}
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '12px',
                      padding: '12px 16px',
                      border: isChecked ? '1.5px solid #2563eb' : '1px solid #e2e8f0',
                      backgroundColor: isChecked ? '#eff6ff' : '#ffffff',
                      borderRadius: '8px',
                      cursor: 'pointer',
                      transition: 'all 0.15s ease',
                    }}
                  >
                    <input
                      type="checkbox"
                      checked={isChecked}
                      onChange={() => handleToggleRole(role.id)}
                      disabled={submitting}
                      style={{ width: '18px', height: '18px', accentColor: '#2563eb' }}
                    />
                    <div>
                      <div style={{ fontWeight: '600', fontSize: '0.9rem', color: '#0f172a' }}>
                        {role.display_name || role.name}
                      </div>
                      {role.description && (
                        <div style={{ fontSize: '0.8rem', color: '#64748b', marginTop: '2px' }}>
                          {role.description}
                        </div>
                      )}
                    </div>
                  </label>
                );
              })}
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
              <button type="button" className="btn-secondary" onClick={onClose} disabled={submitting}>
                Cancel
              </button>
              <button type="submit" className="btn-primary" disabled={submitting}>
                {submitting ? 'Saving...' : 'Save Roles'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}