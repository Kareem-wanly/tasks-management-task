import { useState, useEffect, useCallback } from 'react';
import usersApi from '../../api/usersApi';
import rolesApi from '../../api/rolesApi';
import { useAuth } from '../../context/AuthContext';
import AssignRoleModal from './AssignRoleModal';
import ConfirmModal from '../projects/ConfirmModal';
import './UsersPage.css';

export default function UsersPage() {
  const { user: currentUser, can } = useAuth() || {};

  const checkPermission = (permission) => {
    if (typeof can === 'function') return can(permission);
    if (currentUser?.role === 'admin' || currentUser?.is_admin || currentUser?.role?.name === 'admin') return true;
    if (Array.isArray(currentUser?.permissions)) {
      return currentUser.permissions.includes(permission) || currentUser.permissions.some((p) => (p.name || p) === permission);
    }
    return false;
  };

  const [users, setUsers] = useState([]);
  const [roles, setRoles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [search, setSearch] = useState('');
  const [selectedRole, setSelectedRole] = useState('');
  const [page, setPage] = useState(1);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 10,
  });

  const [userForRoles, setUserForRoles] = useState(null);
  const [userToDelete, setUserToDelete] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  useEffect(() => {
    rolesApi
      .getAll()
      .then((res) => {
        const list = res.data?.data || res.data || [];
        setRoles(Array.isArray(list) ? list : []);
      })
      .catch((err) => console.error('Failed to load roles list:', err));
  }, []);

  const fetchUsers = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      const params = {
        page,
        per_page: 10,
      };

      if (search.trim()) params.search = search.trim();
      if (selectedRole) params.role = selectedRole;

      const res = await usersApi.getAll(params);
      const data = res.data;

      if (data && Array.isArray(data.data)) {
        setUsers(data.data);
        setPagination({
          currentPage: data.current_page || 1,
          lastPage: data.last_page || 1,
          total: data.total || data.data.length,
          perPage: data.per_page || 10,
        });
      } else if (Array.isArray(data)) {
        setUsers(data);
        setPagination({ currentPage: 1, lastPage: 1, total: data.length, perPage: data.length });
      } else {
        setUsers([]);
      }
    } catch (err) {
      console.error('Failed to load users:', err);
      setError(err.data?.message || 'Failed to fetch users list.');
    } finally {
      setLoading(false);
    }
  }, [page, search, selectedRole]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setPage(1);
    fetchUsers();
  };

  const handleRoleFilterChange = (e) => {
    setSelectedRole(e.target.value);
    setPage(1);
  };

  const handleResetFilters = () => {
    setSearch('');
    setSelectedRole('');
    setPage(1);
  };

  const handleDeleteUser = async () => {
    if (!userToDelete) return;
    try {
      setDeleteLoading(true);
      await usersApi.delete(userToDelete.id);
      setUserToDelete(null);
      fetchUsers();
    } catch (err) {
      alert(err.data?.message || 'Failed to delete user.');
    } finally {
      setDeleteLoading(false);
    }
  };

  const canAssignRoles = checkPermission('roles.assign') || checkPermission('roles.manage');
  const canDeleteUser = checkPermission('users.delete');

  return (
    <div className="users-page-container">
      <div className="users-page-header">
        <div>
          <h1>Team & Users</h1>
          <p className="users-subtitle">Manage team members, permissions, and assigned system roles.</p>
        </div>
        <div className="users-stats-pill">
          Total Members: <strong>{pagination.total}</strong>
        </div>
      </div>

      <div className="users-filter-bar">
        <form onSubmit={handleSearchSubmit} className="search-form">
          <div className="search-input-wrapper">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input
              type="text"
              id="users-search-input"
              name="search"
              placeholder="Search by name or email..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <button type="submit" className="filter-btn">Search</button>
        </form>

        <div className="filter-group">
          <select
            id="users-role-filter-select"
            name="roleFilter"
            value={selectedRole}
            onChange={handleRoleFilterChange}
            className="role-filter-select"
          >
            <option value="">All Roles</option>
            {roles.map((r) => (
              <option key={r.id} value={r.name}>
                {r.display_name || r.name}
              </option>
            ))}
          </select>

          {(search || selectedRole) && (
            <button onClick={handleResetFilters} className="btn-reset">
              Reset Filters
            </button>
          )}
        </div>
      </div>

      {error && <div className="alert alert-danger mb-4">{error}</div>}

      <div className="users-table-card">
        {loading ? (
          <div className="users-loading-state">
            <div className="users-spinner" />
            <p>Loading members...</p>
          </div>
        ) : users.length === 0 ? (
          <div className="users-empty-state">
            <div className="empty-icon">👥</div>
            <h3>No members found</h3>
            <p>Try refining your search query or role filter.</p>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="users-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Roles</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => {
                  const isCurrent = currentUser?.id === u.id;
                  const isAdministrator = u.roles?.some(
                    (r) => r.name === 'Administrator' || r.name === 'admin'
                  );

                  return (
                    <tr key={u.id}>
                      <td>
                        <div className="user-profile-cell">
                          <div className="user-avatar-circle">
                            {(u.name || 'U')[0].toUpperCase()}
                          </div>
                          <div>
                            <div className="user-fullname">
                              {u.name} {isCurrent && <span className="tag-current-user">You</span>}
                            </div>
                            <div className="user-email-text">{u.email}</div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div className="roles-badges-list">
                          {u.roles && u.roles.length > 0 ? (
                            u.roles.map((r) => (
                              <span
                                key={r.id}
                                className={`role-badge ${
                                  r.name === 'Administrator' ? 'role-admin' : 'role-member'
                                }`}
                              >
                                {r.display_name || r.name}
                              </span>
                            ))
                          ) : (
                            <span className="no-roles-badge">No Roles</span>
                          )}
                        </div>
                      </td>
                      <td>
                        <div className="table-actions-cell">
                          {canAssignRoles && (
                            <button
                              className="action-btn-role"
                              onClick={() => setUserForRoles(u)}
                            >
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                              Manage Roles
                            </button>
                          )}

                          {canDeleteUser && !isCurrent && !isAdministrator && (
                            <button
                              className="action-btn-delete"
                              onClick={() => setUserToDelete(u)}
                              title="Delete user"
                            >
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {pagination.lastPage > 1 && (
          <div className="pagination-bar">
            <span className="pagination-info">
              Showing page {pagination.currentPage} of {pagination.lastPage}
            </span>
            <div className="pagination-controls">
              <button
                className="btn-page"
                onClick={() => setPage((prev) => Math.max(prev - 1, 1))}
                disabled={pagination.currentPage <= 1 || loading}
              >
                Previous
              </button>
              <button
                className="btn-page"
                onClick={() => setPage((prev) => Math.min(prev + 1, pagination.lastPage))}
                disabled={pagination.currentPage >= pagination.lastPage || loading}
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {userForRoles && (
        <AssignRoleModal
          isOpen={Boolean(userForRoles)}
          onClose={() => setUserForRoles(null)}
          onSuccess={() => {
            setUserForRoles(null);
            fetchUsers();
          }}
          user={userForRoles}
        />
      )}

      {userToDelete && (
        <ConfirmModal
          isOpen={Boolean(userToDelete)}
          title="Delete Team Member"
          message={`Are you sure you want to delete user "${userToDelete.name}" (${userToDelete.email})?`}
          confirmText="Delete Member"
          danger={true}
          loading={deleteLoading}
          onConfirm={handleDeleteUser}
          onClose={() => setUserToDelete(null)}
        />
      )}
    </div>
  );
}