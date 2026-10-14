import { useState, useEffect, useMemo } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import rolesApi from '../../api/rolesApi';
import './RoleFormPage.css';

export default function RoleFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEditing = Boolean(id && id !== 'create');

  const [formData, setFormData] = useState({
    name: '',
    display_name: '',
    description: '',
  });

  const [roleDetails, setRoleDetails] = useState(null);
  const [allPermissions, setAllPermissions] = useState([]);
  const [selectedPermissionIds, setSelectedPermissionIds] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [validationErrors, setValidationErrors] = useState({});

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setError(null);

        const permsRes = await rolesApi.getPermissions();
        const rawPerms = permsRes?.data || permsRes || {};
        const permsList =
          rawPerms.permissions ||
          rawPerms.data ||
          (Array.isArray(rawPerms) ? rawPerms : []);
        setAllPermissions(Array.isArray(permsList) ? permsList : []);

        if (isEditing) {
          const roleRes = await rolesApi.getById(id);
          const rawRole = roleRes?.data || roleRes || {};
          const role = rawRole.role || rawRole.data || rawRole;

          if (role && (role.name || role.id)) {
            setRoleDetails(role);
            setFormData({
              name: role.name || '',
              display_name: role.display_name || '',
              description: role.description || '',
            });

            if (Array.isArray(role.permissions)) {
              const assignedIds = role.permissions.map((p) => Number(p.id ?? p));
              setSelectedPermissionIds(assignedIds);
            }
          }
        }
      } catch (err) {
        console.error('Failed to load role form data:', err);
        setError(err.data?.message || err.message || 'Failed to load role data.');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [id, isEditing]);

  const groupedPermissions = useMemo(() => {
    const filtered = allPermissions.filter((p) => {
      if (!searchQuery.trim()) return true;
      const query = searchQuery.toLowerCase();
      return (
        p.name.toLowerCase().includes(query) ||
        (p.description && p.description.toLowerCase().includes(query))
      );
    });

    return filtered.reduce((acc, perm) => {
      const group = perm.name.split('.')[0] || 'general';
      if (!acc[group]) acc[group] = [];
      acc[group].push(perm);
      return acc;
    }, {});
  }, [allPermissions, searchQuery]);

  const isSystemRole = isEditing && ['Administrator', 'Project Manager', 'Member'].includes(roleDetails?.name);

  const handleTogglePermission = (permId) => {
    const idNum = Number(permId);
    setSelectedPermissionIds((prev) =>
      prev.includes(idNum) ? prev.filter((i) => i !== idNum) : [...prev, idNum]
    );
  };

  const handleToggleCategory = (permsInGroup) => {
    const groupIds = permsInGroup.map((p) => Number(p.id));
    const allSelected = groupIds.every((gid) => selectedPermissionIds.includes(gid));

    if (allSelected) {
      setSelectedPermissionIds((prev) => prev.filter((gid) => !groupIds.includes(gid)));
    } else {
      setSelectedPermissionIds((prev) => Array.from(new Set([...prev, ...groupIds])));
    }
  };

  const handleSelectAllGlobal = () => {
    if (selectedPermissionIds.length === allPermissions.length) {
      setSelectedPermissionIds([]);
    } else {
      setSelectedPermissionIds(allPermissions.map((p) => Number(p.id)));
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setValidationErrors({});

    const payload = {
      ...formData,
      permissions: selectedPermissionIds,
    };

    try {
      if (isEditing) {
        await rolesApi.update(id, payload);
      } else {
        await rolesApi.create(payload);
      }
      navigate('/roles');
    } catch (err) {
      console.error('Save failed:', err);
      if (err.data?.errors) {
        setValidationErrors(err.data.errors);
      } else {
        setError(err.data?.message || err.message || 'Failed to save role changes.');
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="role-form-page-container">
        <div className="role-form-loading">
          <div className="spinner" />
          <p>Loading role configuration and permissions matrix...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="role-form-page-container">
      <div className="role-form-header">
        <div className="header-breadcrumbs">
          <Link to="/roles" className="back-link">← Back to Roles</Link>
          <h1>{isEditing ? `Edit Role: ${formData.display_name || formData.name}` : 'Create New Role'}</h1>
          <p className="header-sub">
            {isEditing
              ? 'Configure permissions matrix and properties for this system role.'
              : 'Define a new access role and select granular permissions for assigned users.'}
          </p>
        </div>

        <div className="header-actions">
          <button
            type="button"
            className="btn-cancel"
            onClick={() => navigate('/roles')}
            disabled={saving}
          >
            Cancel
          </button>
          <button
            type="submit"
            form="role-editor-form"
            className="btn-save"
            disabled={saving}
          >
            {saving ? 'Saving Changes...' : isEditing ? 'Save Role Changes' : 'Create Role'}
          </button>
        </div>
      </div>

      {error && <div className="alert-banner danger">{error}</div>}

      <form id="role-editor-form" onSubmit={handleSubmit} className="role-form-layout">
        <div className="role-form-sidebar">
          <div className="form-card">
            <h3>Role Information</h3>
            <p className="form-card-hint">Identity and metadata for role classification.</p>

            <div className="form-group">
              <label>
                Display Name <span className="req">*</span>
              </label>
              <input
                type="text"
                placeholder="e.g. Lead Developer"
                value={formData.display_name}
                onChange={(e) => setFormData({ ...formData, display_name: e.target.value })}
                className={validationErrors.display_name ? 'input-error' : ''}
                required
              />
              {validationErrors.display_name && (
                <span className="error-msg">{validationErrors.display_name[0]}</span>
              )}
            </div>

            <div className="form-group">
              <label>
                System Key / Identifier <span className="req">*</span>
              </label>
              <input
                type="text"
                disabled={isSystemRole}
                placeholder="e.g. lead_developer"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                className={validationErrors.name ? 'input-error' : ''}
                required
              />
              {validationErrors.name && (
                <span className="error-msg">{validationErrors.name[0]}</span>
              )}
              {isSystemRole && (
                <span className="hint-msg">Protected system slug cannot be renamed.</span>
              )}
            </div>

            <div className="form-group">
              <label>Role Description</label>
              <textarea
                rows={3}
                placeholder="Briefly describe what this role allows..."
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              />
            </div>

            {isEditing && (
              <div className="role-meta-summary">
                <div className="meta-row">
                  <span>Assigned Users:</span>
                  <strong>{roleDetails?.users_count ?? (roleDetails?.users?.length || 0)}</strong>
                </div>
                <div className="meta-row">
                  <span>Role Type:</span>
                  <strong>{isSystemRole ? 'System Protected' : 'Custom'}</strong>
                </div>
              </div>
            )}
          </div>
        </div>

        <div className="role-form-main">
          <div className="form-card">
            <div className="permissions-toolbar">
              <div>
                <h3>Assigned Permissions</h3>
                <span className="assigned-counter-badge">
                  {selectedPermissionIds.length} of {allPermissions.length} Enabled
                </span>
              </div>

              <div className="toolbar-controls">
                <input
                  type="text"
                  placeholder="Filter permissions..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="permission-filter-input"
                />
                <button
                  type="button"
                  onClick={handleSelectAllGlobal}
                  className="btn-select-all"
                >
                  {selectedPermissionIds.length === allPermissions.length ? 'Deselect All' : 'Select All'}
                </button>
              </div>
            </div>

            <div className="permission-groups-container">
              {Object.keys(groupedPermissions).length === 0 ? (
                <div className="empty-permissions-search">
                  No permissions matching "{searchQuery}"
                </div>
              ) : (
                Object.entries(groupedPermissions).map(([category, perms]) => {
                  const categoryIds = perms.map((p) => Number(p.id));
                  const isCatAllSelected = categoryIds.every((id) => selectedPermissionIds.includes(id));
                  const selectedInCat = categoryIds.filter((id) => selectedPermissionIds.includes(id)).length;

                  return (
                    <div key={category} className="permission-category-card">
                      <div className="category-header">
                        <div className="category-title-wrap">
                          <span className="category-name">{category.toUpperCase()} PERMISSIONS</span>
                          <span className="category-count">
                            ({selectedInCat}/{perms.length})
                          </span>
                        </div>
                        <button
                          type="button"
                          onClick={() => handleToggleCategory(perms)}
                          className="btn-cat-toggle"
                        >
                          {isCatAllSelected ? 'Deselect Category' : 'Select Category'}
                        </button>
                      </div>

                      <div className="permissions-checkbox-grid">
                        {perms.map((perm) => {
                          const isChecked = selectedPermissionIds.includes(Number(perm.id));

                          return (
                            <label
                              key={perm.id}
                              className={`permission-item ${isChecked ? 'active' : ''}`}
                            >
                              <input
                                type="checkbox"
                                checked={isChecked}
                                onChange={() => handleTogglePermission(perm.id)}
                              />
                              <div className="perm-info">
                                <span className="perm-name">{perm.name}</span>
                                {perm.description && (
                                  <span className="perm-desc">{perm.description}</span>
                                )}
                              </div>
                            </label>
                          );
                        })}
                      </div>
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </div>
      </form>
    </div>
  );
}