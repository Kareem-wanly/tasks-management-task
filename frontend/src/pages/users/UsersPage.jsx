import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import usersApi from '../../api/usersApi';
import rolesApi from '../../api/rolesApi';
import useDebounce from '../../hooks/useDebounce';
import UserModal from './UserModal';
import ConfirmModal from '../projects/ConfirmModal';
import { useAuth } from '../../context/AuthContext';
import './UsersPage.css';
import { useToast } from '../../context/ToastContext';


export default function UsersPage() {
  const { can } = useAuth() || {};
  const [searchParams, setSearchParams] = useSearchParams();
  const { showToast } = useToast();


  const initialSearch = searchParams.get('search') || '';
  const initialRole = searchParams.get('role') || '';
  const initialPage = parseInt(searchParams.get('page') || '1', 10);

  const [searchTerm, setSearchTerm] = useState(initialSearch);
  const [selectedRole, setSelectedRole] = useState(initialRole);
  const [currentPage, setCurrentPage] = useState(initialPage);

  const debouncedSearch = useDebounce(searchTerm, 400);

  const [users, setUsers] = useState([]);
  const [roles, setRoles] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [userToEdit, setUserToEdit] = useState(null);
  const [userToDelete, setUserToDelete] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  useEffect(() => {
    const params = {};
    if (debouncedSearch.trim()) params.search = debouncedSearch.trim();
    if (selectedRole) params.role = selectedRole;
    if (currentPage > 1) params.page = currentPage.toString();

    setSearchParams(params, { replace: true });
  }, [debouncedSearch, selectedRole, currentPage, setSearchParams]);

  useEffect(() => {
    const loadRoles = async () => {
      try {
        const res = await rolesApi.getAll();
        const list = res.data?.data || res.data?.roles || res.data || [];
        setRoles(Array.isArray(list) ? list : []);
      } catch (err) {
        console.error('Failed to load roles for filter:', err);
      }
    };
    loadRoles();
  }, []);

  const fetchUsers = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await usersApi.getAll({
        search: debouncedSearch,
        role: selectedRole,
        page: currentPage,
      });

      const data = res.data?.data || res.data?.users || res.data || [];
      setUsers(Array.isArray(data) ? data : []);

      if (res.data?.meta || res.data?.current_page) {
        setMeta({
          current_page: res.data.meta?.current_page || res.data.current_page || 1,
          last_page: res.data.meta?.last_page || res.data.last_page || 1,
          total: res.data.meta?.total || res.data.total || (Array.isArray(data) ? data.length : 0),
        });
      }
    } catch (err) {
      console.error('Failed to load users:', err);
      setError(err.data?.message || 'Failed to fetch users list.');
    } finally {
      setLoading(false);
    }
  }, [debouncedSearch, selectedRole, currentPage]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  const handleSearchChange = (e) => {
    setSearchTerm(e.target.value);
    setCurrentPage(1);
  };

  const handleRoleChange = (e) => {
    setSelectedRole(e.target.value);
    setCurrentPage(1);
  };

  const handleDeleteUser = async () => {
    if (!userToDelete) return;
    try {
      setDeleteLoading(true);
      await usersApi.delete(userToDelete.id);
      showToast(`User "${userToDelete.name}" was deleted successfully.`, 'success');
      setUserToDelete(null);
      fetchUsers();
    } catch (err) {
      showToast(err.data?.message || 'Failed to delete user.', 'error');
    } finally {
      setDeleteLoading(false);
    }
  };

  const canCreate = typeof can === 'function' ? can('users.create') : true;
  const canEdit = typeof can === 'function' ? can('users.update') : true;
  const canDelete = typeof can === 'function' ? can('users.delete') : true;

  return (
    <div className="users-page-container">
      <div className="users-header">
        <div>
          <h1>Users Management</h1>
          <p className="users-subtitle">Manage team members, accounts, and system role assignments.</p>
        </div>
        {canCreate && (
          <button
            className="btn-create-user"
            onClick={() => {
              setUserToEdit(null);
              setIsModalOpen(true);
            }}
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add New User
          </button>
        )}
      </div>

      <div className="users-filters-bar">
        <div className="search-box-wrapper">
          <svg className="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            type="text"
            placeholder="Search by name or email..."
            value={searchTerm}
            onChange={handleSearchChange}
            className="users-search-input"
          />
          {searchTerm && (
            <button className="btn-clear-search" onClick={() => { setSearchTerm(''); setCurrentPage(1); }}>×</button>
          )}
        </div>

        <select
          value={selectedRole}
          onChange={handleRoleChange}
          className="users-role-select"
        >
          <option value="">All System Roles</option>
          {roles.map((r) => (
            <option key={r.id} value={r.name}>{r.display_name || r.name}</option>
          ))}
        </select>
      </div>

      {error && <div className="alert alert-danger mb-4">{error}</div>}

      {loading ? (
        <div className="users-loading-state">
          <div className="users-spinner" />
          <p>Fetching users...</p>
        </div>
      ) : users.length === 0 ? (
        <div className="users-empty-state">
          <div className="empty-icon">👥</div>
          <h3>No users found</h3>
          <p>Try adjusting your search query or filter options.</p>
        </div>
      ) : (
        <>
          <div className="users-table-card">
            <table className="users-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Joined Date</th>
                  {(canEdit || canDelete) && <th style={{ textAlign: 'right' }}>Actions</th>}
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id}>
                    <td>
                      <div className="user-identity">
                        <div className="user-avatar-circle">
                          {u.name?.charAt(0)?.toUpperCase() || 'U'}
                        </div>
                        <div>
                          <div className="user-fullname">{u.name}</div>
                          <div className="user-email-sub">{u.email}</div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span className="badge-role-tag">
                        {u.role?.display_name || u.role?.name || u.role || 'No Role'}
                      </span>
                    </td>
                    <td>
                      <span className={`badge-status ${u.is_active === false ? 'inactive' : 'active'}`}>
                        {u.is_active === false ? 'Inactive' : 'Active'}
                      </span>
                    </td>
                    <td className="user-date-col">
                      {u.created_at ? new Date(u.created_at).toLocaleDateString() : 'N/A'}
                    </td>
                    {(canEdit || canDelete) && (
                      <td style={{ textAlign: 'right' }}>
                        <div className="table-actions">
                          {canEdit && (
                            <button
                              className="btn-table-action edit"
                              onClick={() => {
                                setUserToEdit(u);
                                setIsModalOpen(true);
                              }}
                              title="Edit user"
                            >
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                          )}
                          {canDelete && (
                            <button
                              className="btn-table-action delete"
                              onClick={() => setUserToDelete(u)}
                              title="Delete user"
                            >
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                          )}
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {meta.last_page > 1 && (
            <div className="users-pagination">
              <span className="pagination-info">
                Showing page <strong>{meta.current_page}</strong> of <strong>{meta.last_page}</strong> ({meta.total} total users)
              </span>
              <div className="pagination-buttons">
                <button
                  disabled={currentPage <= 1}
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  className="btn-page"
                >
                  Previous
                </button>
                <button
                  disabled={currentPage >= meta.last_page}
                  onClick={() => setCurrentPage((p) => p + 1)}
                  className="btn-page"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {isModalOpen && (
        <UserModal
          isOpen={isModalOpen}
          userToEdit={userToEdit}
          roles={roles}
          onClose={() => {
            setIsModalOpen(false);
            setUserToEdit(null);
          }}
          onSuccess={() => {
            setIsModalOpen(false);
            setUserToEdit(null);
            fetchUsers();
          }}
        />
      )}

      {userToDelete && (
        <ConfirmModal
          isOpen={Boolean(userToDelete)}
          title="Delete User"
          message={`Are you sure you want to delete "${userToDelete.name}"? This action cannot be undone.`}
          confirmText="Delete User"
          danger={true}
          loading={deleteLoading}
          onConfirm={handleDeleteUser}
          onClose={() => setUserToDelete(null)}
        />
      )}
    </div>
  );
}