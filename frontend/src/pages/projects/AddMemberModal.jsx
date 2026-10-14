import { useState, useEffect } from 'react';
import usersApi from '../../api/usersApi';
import projectsApi from '../../api/projectsApi';

export default function AddMemberModal({ isOpen, onClose, onSuccess, projectId, existingMembers = [], ownerId }) {
  const [users, setUsers] = useState([]);
  const [selectedUserId, setSelectedUserId] = useState('');
  const [loadingUsers, setLoadingUsers] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (isOpen) {
      setError(null);
      setSelectedUserId('');
      fetchAvailableUsers();
    }
  }, [isOpen]);

  const fetchAvailableUsers = async () => {
    try {
      setLoadingUsers(true);
      const res = await usersApi.getAll();
      const allUsers = res?.data?.data || res?.data || res || [];
      
      const currentMemberIds = new Set([
        ...(existingMembers || []).map((m) => m.id),
        ownerId,
      ]);

      const available = allUsers.filter((u) => !currentMemberIds.has(u.id));
      setUsers(available);
      
      if (available.length > 0) {
        setSelectedUserId(available[0].id);
      }
    } catch (err) {
      console.error('Failed to load users:', err);
      setError('Failed to fetch available users list.');
    } finally {
      setLoadingUsers(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!selectedUserId) {
      setError('Please select a user to add.');
      return;
    }

    try {
      setSubmitting(true);
      setError(null);
      await projectsApi.addMember(projectId, { user_id: selectedUserId });
      onSuccess();
      onClose();
    } catch (err) {
      console.error('Failed to add member:', err);
      setError(err.data?.message || err.message || 'Failed to add member to the project.');
    } finally {
      setSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="modal-backdrop">
      <div className="modal-card">
        <div className="modal-header">
          <h3>Add Team Member</h3>
          <button className="modal-close-btn" onClick={onClose} disabled={submitting}>
            ✕
          </button>
        </div>

        {error && <div className="modal-error-banner">{error}</div>}

        {loadingUsers ? (
          <div className="modal-loading-state">
            <div className="spinner" />
            <span>Loading available users...</span>
          </div>
        ) : users.length === 0 ? (
          <div className="modal-empty-state">
            <p>All system users are already members of this project.</p>
            <div className="modal-actions" style={{ marginTop: '1.5rem' }}>
              <button type="button" className="btn-secondary" onClick={onClose}>
                Close
              </button>
            </div>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label htmlFor="select-user">Select User <span className="req">*</span></label>
              <select
                id="select-user"
                value={selectedUserId}
                onChange={(e) => setSelectedUserId(e.target.value)}
                disabled={submitting}
                className="form-control"
              >
                {users.map((user) => (
                  <option key={user.id} value={user.id}>
                    {user.name} ({user.email})
                  </option>
                ))}
              </select>
            </div>

            <div className="modal-actions">
              <button
                type="button"
                className="btn-secondary"
                onClick={onClose}
                disabled={submitting}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn-primary"
                disabled={submitting || !selectedUserId}
              >
                {submitting ? 'Adding...' : 'Add to Project'}
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}