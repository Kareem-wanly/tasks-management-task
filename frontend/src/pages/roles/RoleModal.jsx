import { useState, useEffect } from 'react';
import rolesApi from '../../api/rolesApi';

export default function RoleModal({ isOpen, onClose, onSuccess, roleToEdit }) {
  const isEditing = Boolean(roleToEdit);

  const [formData, setFormData] = useState({
    name: '',
    display_name: '',
    description: '',
  });

  const [allPermissions, setAllPermissions] = useState([]);
  const [selectedPermissionIds, setSelectedPermissionIds] = useState([]);
  const [loading, setLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!isOpen) return;

    setError(null);
    setLoading(true);

    if (roleToEdit) {
      setFormData({
        name: roleToEdit.name || '',
        display_name: roleToEdit.display_name || '',
        description: roleToEdit.description || '',
      });
      setSelectedPermissionIds(
        roleToEdit.permissions ? roleToEdit.permissions.map((p) => p.id) : []
      );
    } else {
      setFormData({ name: '', display_name: '', description: '' });
      setSelectedPermissionIds([]);
    }

    rolesApi
      .getPermissions()
      .then((res) => {
        const list = res.data?.data || res.data?.permissions || res.data || [];
        setAllPermissions(Array.isArray(list) ? list : []);
      })
      .catch((err) => {
        console.error('Failed to load permissions:', err);
        setError('Failed to load permissions list.');
      })
      .finally(() => setLoading(false));
  }, [isOpen, roleToEdit]);

  if (!isOpen) return null;

  const handleTogglePermission = (permId) => {
    setSelectedPermissionIds((prev) =>
      prev.includes(permId) ? prev.filter((id) => id !== permId) : [...prev, permId]
    );
  };

  const handleToggleGroup = (groupPermIds) => {
    const allSelected = groupPermIds.every((id) => selectedPermissionIds.includes(id));
    if (allSelected) {
      setSelectedPermissionIds((prev) => prev.filter((id) => !groupPermIds.includes(id)));
    } else {
      setSelectedPermissionIds((prev) => Array.from(new Set([...prev, ...groupPermIds])));
    }
  };

  const groupedPermissions = allPermissions.reduce((acc, perm) => {
    const group = perm.name.split('.')[0] || 'general';
    if (!acc[group]) acc[group] = [];
    acc[group].push(perm);
    return acc;
  }, {});

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!formData.name.trim() || !formData.display_name.trim()) {
      setError('Role system name and display name are required.');
      return;
    }

    setSubmitting(true);
    setError(null);

    const payload = {
      ...formData,
      permissions: selectedPermissionIds,
    };

    try {
      if (isEditing) {
        await rolesApi.update(roleToEdit.id, payload);
      } else {
        await rolesApi.create(payload);
      }
      onSuccess();
    } catch (err) {
      console.error('Failed to save role:', err);
      setError(err.data?.message || 'Failed to save role.');
    } finally {
      setSubmitting(false);
    }
  };

  const isSystemRole = isEditing && ['Administrator', 'Project Manager', 'Member'].includes(roleToEdit?.name);

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '680px', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}>
        <div className="modal-header">
          <h3>{isEditing ? `Edit Role: ${roleToEdit.display_name || roleToEdit.name}` : 'Create New Role'}</h3>
          <button className="modal-close-btn" onClick={onClose} disabled={submitting}>✕</button>
        </div>

        {error && <div className="alert alert-danger" style={{ marginBottom: '16px' }}>{error}</div>}

        {loading ? (
          <div style={{ padding: '32px', textAlign: 'center', color: '#64748b' }}>Loading permissions...</div>
        ) : (
          <form onSubmit={handleSubmit} style={{ overflowY: 'auto', paddingRight: '4px' }}>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '14px', marginBottom: '14px' }}>
              <div>
                <label style={{ display: 'block', fontSize: '0.85rem', fontWeight: '600', marginBottom: '6px' }}>
                  Display Name <span style={{ color: '#ef4444' }}>*</span>
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Quality Assurance"
                  value={formData.display_name}
                  onChange={(e) => setFormData({ ...formData, display_name: e.target.value })}
                  style={{ width: '100%', padding: '9px 12px', border: '1.5px solid #cbd5e1', borderRadius: '8px' }}
                />
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '0.85rem', fontWeight: '600', marginBottom: '6px' }}>
                  System Key / Slug <span style={{ color: '#ef4444' }}>*</span>
                </label>
                <input
                  type="text"
                  required
                  disabled={isSystemRole}
                  placeholder="e.g. qa_engineer"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  style={{ width: '100%', padding: '9px 12px', border: '1.5px solid #cbd5e1', borderRadius: '8px', backgroundColor: isSystemRole ? '#f8fafc' : '#ffffff' }}
                />
              </div>
            </div>

            <div style={{ marginBottom: '18px' }}>
              <label style={{ display: 'block', fontSize: '0.85rem', fontWeight: '600', marginBottom: '6px' }}>
                Description
              </label>
              <textarea
                rows={2}
                placeholder="Briefly describe what this role does..."
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                style={{ width: '100%', padding: '9px 12px', border: '1.5px solid #cbd5e1', borderRadius: '8px', resize: 'vertical' }}
              />
            </div>

            <div>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
                <label style={{ fontSize: '0.9rem', fontWeight: '700', color: '#0f172a' }}>
                  Assign Permissions ({selectedPermissionIds.length} selected)
                </label>
                <button
                  type="button"
                  style={{ background: 'none', border: 'none', color: '#2563eb', fontSize: '0.8rem', fontWeight: '600', cursor: 'pointer' }}
                  onClick={() => {
                    if (selectedPermissionIds.length === allPermissions.length) {
                      setSelectedPermissionIds([]);
                    } else {
                      setSelectedPermissionIds(allPermissions.map((p) => p.id));
                    }
                  }}
                >
                  {selectedPermissionIds.length === allPermissions.length ? 'Deselect All' : 'Select All'}
                </button>
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '14px', marginBottom: '24px' }}>
                {Object.entries(groupedPermissions).map(([group, perms]) => {
                  const groupIds = perms.map((p) => p.id);
                  const isAllGroupSelected = groupIds.every((id) => selectedPermissionIds.includes(id));

                  return (
                    <div key={group} style={{ border: '1px solid #e2e8f0', borderRadius: '8px', padding: '12px 14px', backgroundColor: '#fafbfc' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px', borderBottom: '1px solid #e2e8f0', paddingBottom: '6px' }}>
                        <span style={{ fontWeight: '700', fontSize: '0.85rem', textTransform: 'uppercase', color: '#475569', letterSpacing: '0.04em' }}>
                          {group} Management
                        </span>
                        <button
                          type="button"
                          onClick={() => handleToggleGroup(groupIds)}
                          style={{ background: 'none', border: 'none', color: '#64748b', fontSize: '0.75rem', cursor: 'pointer' }}
                        >
                          {isAllGroupSelected ? 'Deselect group' : 'Select group'}
                        </button>
                      </div>

                      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '8px' }}>
                        {perms.map((perm) => {
                          const isChecked = selectedPermissionIds.includes(perm.id);
                          return (
                            <label
                              key={perm.id}
                              style={{
                                display: 'flex',
                                alignItems: 'flex-start',
                                gap: '8px',
                                fontSize: '0.8rem',
                                color: '#1e293b',
                                cursor: 'pointer',
                                padding: '4px',
                              }}
                            >
                              <input
                                type="checkbox"
                                checked={isChecked}
                                onChange={() => handleTogglePermission(perm.id)}
                                style={{ marginTop: '2px', accentColor: '#2563eb' }}
                              />
                              <div>
                                <div style={{ fontWeight: '600' }}>{perm.name}</div>
                                {perm.description && <div style={{ fontSize: '0.725rem', color: '#64748b' }}>{perm.description}</div>}
                              </div>
                            </label>
                          );
                        })}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', borderTop: '1px solid #e2e8f0', paddingTop: '16px' }}>
              <button type="button" className="btn-secondary" onClick={onClose} disabled={submitting}>
                Cancel
              </button>
              <button type="submit" className="btn-primary" disabled={submitting}>
                {submitting ? 'Saving...' : isEditing ? 'Update Role' : 'Create Role'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}