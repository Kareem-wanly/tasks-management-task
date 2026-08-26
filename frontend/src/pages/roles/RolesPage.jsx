import { useState, useEffect, useCallback } from 'react';
import rolesApi from '../../api/rolesApi';
import { useAuth } from '../../context/AuthContext';
import RoleModal from './RoleModal';
import ConfirmModal from '../projects/ConfirmModal';
import './RolesPage.css';
import { Link } from 'react-router-dom';


export default function RolesPage() {
  const { user: currentUser, can } = useAuth() || {};

  const checkPermission = (permission) => {
    if (typeof can === 'function') return can(permission);
    if (currentUser?.role === 'admin' || currentUser?.is_admin || currentUser?.role?.name === 'admin') return true;
    if (Array.isArray(currentUser?.permissions)) {
      return currentUser.permissions.includes(permission) || currentUser.permissions.some((p) => (p.name || p) === permission);
    }
    return false;
  };

  const [roles, setRoles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [roleToEdit, setRoleToEdit] = useState(null);
  const [roleToDelete, setRoleToDelete] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const fetchRoles = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await rolesApi.getAll();
      const list = res.data?.data || res.data?.roles || res.data || [];
      setRoles(Array.isArray(list) ? list : []);
    } catch (err) {
      console.error('Failed to load roles:', err);
      setError(err.data?.message || 'Failed to fetch roles list.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchRoles();
  }, [fetchRoles]);

  const handleDeleteRole = async () => {
    if (!roleToDelete) return;
    try {
      setDeleteLoading(true);
      await rolesApi.delete(roleToDelete.id);
      setRoleToDelete(null);
      fetchRoles();
    } catch (err) {
      alert(err.data?.message || 'Failed to delete role.');
    } finally {
      setDeleteLoading(false);
    }
  };

  const canCreateRole = checkPermission('roles.create') || checkPermission('roles.manage');
  const canEditRole = checkPermission('roles.update') || checkPermission('roles.manage');
  const canDeleteRole = checkPermission('roles.delete') || checkPermission('roles.manage');

  const protectedRoleNames = ['Administrator', 'Project Manager', 'Member'];

  return (
    <div className="roles-page-container">
      <div className="roles-page-header">
        <div>
          <h1>Roles & Permissions</h1>
          <p className="roles-subtitle">Define system roles, access policies, and permission boundaries.</p>
        </div>
        {canCreateRole && (
          <Link to="/roles/create" className="btn-create-role" style={{ textDecoration: 'none' }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Create New Role
                </Link>
        )}
      </div>

      {error && <div className="alert alert-danger mb-4">{error}</div>}

      {loading ? (
        <div className="roles-loading-state">
          <div className="roles-spinner" />
          <p>Loading roles and permissions...</p>
        </div>
      ) : roles.length === 0 ? (
        <div className="roles-empty-state">
          <div className="empty-icon">🛡️</div>
          <h3>No roles defined yet</h3>
          <p>Create roles to configure granular access control for your team.</p>
        </div>
      ) : (
        <div className="roles-grid">
          {roles.map((role) => {
            const isProtected = protectedRoleNames.includes(role.name);
            const permissionsCount = role.permissions_count ?? (role.permissions ? role.permissions.length : 0);
            const usersCount = role.users_count ?? (role.users ? role.users.length : 0);

            return (
              <div key={role.id} className="role-card">
                <div className="role-card-header">
                  <div>
                    <div className="role-card-title-row">
                      <h3 className="role-title">{role.display_name || role.name}</h3>
                      {isProtected && <span className="badge-system-role">System</span>}
                    </div>
                    <div className="role-slug-code">{role.name}</div>
                  </div>
                </div>

                <p className="role-card-description">
                  {role.description || 'No description provided for this role.'}
                </p>

                <div className="role-metrics-row">
                  <div className="metric-pill">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span><strong>{permissionsCount}</strong> Permissions</span>
                  </div>

                  <div className="metric-pill">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span><strong>{usersCount}</strong> Users</span>
                  </div>
                </div>

                <div className="role-card-footer">
                  <div className="role-actions">
                    {canEditRole && (
                      <Link
        to={`/roles/${role.id}`}
        className="btn-role-action edit"
        style={{ textDecoration: 'none' }}
        >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
        </svg>
        Edit Permissions
        </Link>
                    )}

                    {canDeleteRole && !isProtected && (
                      <button
                        className="btn-role-action delete"
                        onClick={() => setRoleToDelete(role)}
                        title="Delete custom role"
                      >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {isModalOpen && (
        <RoleModal
          isOpen={isModalOpen}
          roleToEdit={roleToEdit}
          onClose={() => {
            setIsModalOpen(false);
            setRoleToEdit(null);
          }}
          onSuccess={() => {
            setIsModalOpen(false);
            setRoleToEdit(null);
            fetchRoles();
          }}
        />
      )}

      {roleToDelete && (
        <ConfirmModal
          isOpen={Boolean(roleToDelete)}
          title="Delete Role"
          message={`Are you sure you want to delete the role "${roleToDelete.display_name || roleToDelete.name}"? Users with this role will lose these permissions.`}
          confirmText="Delete Role"
          danger={true}
          loading={deleteLoading}
          onConfirm={handleDeleteRole}
          onClose={() => setRoleToDelete(null)}
        />
      )}
    </div>
  );
}