import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import tasksApi from '../../api/tasksApi';
import { useAuth } from '../../context/AuthContext';
import TaskFormModal from '../projects/TaskFormModal';
import ConfirmModal from '../projects/ConfirmModal';
import './TaskDetailsPage.css';

export default function TaskDetailsPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user, can } = useAuth() || {};

  const checkPermission = (permission) => {
    if (typeof can === 'function') return can(permission);
    if (user?.role === 'admin' || user?.is_admin || user?.role?.name === 'admin') return true;
    if (Array.isArray(user?.permissions)) {
      return user.permissions.includes(permission) || user.permissions.some((p) => (p.name || p) === permission);
    }
    return false;
  };

  const [task, setTask] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [isEditOpen, setIsEditOpen] = useState(false);
  const [isDeleteOpen, setIsDeleteOpen] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);
  const [statusLoading, setStatusLoading] = useState(false);

  const [commentBody, setCommentBody] = useState('');
  const [commentSubmitting, setCommentSubmitting] = useState(false);
  const [commentError, setCommentError] = useState(null);
  const [commentToDelete, setCommentToDelete] = useState(null);
  const [deleteCommentLoading, setDeleteCommentLoading] = useState(false);

  const fetchTaskDetails = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await tasksApi.getById(id);
      const data = res.data?.data || res.data || res;
      setTask(data);
    } catch (err) {
      console.error('Failed to load task:', err);
      setError(err.data?.message || 'Failed to load task details.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    fetchTaskDetails();
  }, [fetchTaskDetails]);

  const handleStatusChange = async (newStatus) => {
    if (!task || task.status === newStatus) return;
    try {
      setStatusLoading(true);
      await tasksApi.updateStatus(task.id, newStatus);
      setTask((prev) => ({ ...prev, status: newStatus }));
    } catch (err) {
      alert(err.data?.message || 'Failed to update task status.');
    } finally {
      setStatusLoading(false);
    }
  };

  const handleDeleteTask = async () => {
    try {
      setActionLoading(true);
      await tasksApi.delete(task.id);
      setIsDeleteOpen(false);
      navigate('/tasks');
    } catch (err) {
      alert(err.data?.message || 'Failed to delete task.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleAddComment = async (e) => {
    e.preventDefault();
    if (!commentBody.trim()) return;

    try {
      setCommentSubmitting(true);
      setCommentError(null);
      const res = await tasksApi.addComment(task.id, commentBody.trim());
      const newComment = res.data?.data || res.data || res;

      setTask((prev) => ({
        ...prev,
        comments: [...(prev.comments || []), newComment],
      }));
      setCommentBody('');
    } catch (err) {
      console.error('Failed to add comment:', err);
      setCommentError(err.data?.message || 'Failed to post comment.');
    } finally {
      setCommentSubmitting(false);
    }
  };

  const handleDeleteComment = async () => {
    if (!commentToDelete) return;
    try {
      setDeleteCommentLoading(true);
      await tasksApi.deleteComment(commentToDelete.id);
      setTask((prev) => ({
        ...prev,
        comments: (prev.comments || []).filter((c) => c.id !== commentToDelete.id),
      }));
      setCommentToDelete(null);
    } catch (err) {
      alert(err.data?.message || 'Failed to delete comment.');
    } finally {
      setDeleteCommentLoading(false);
    }
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const formatDateTime = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (loading) {
    return (
      <div className="task-details-loading">
        <div className="task-spinner" />
        <p>Loading task details...</p>
      </div>
    );
  }

  if (error || !task) {
    return (
      <div className="task-details-container">
        <div className="task-alert task-alert-danger">{error || 'Task not found.'}</div>
        <button className="task-btn task-btn-secondary" onClick={() => navigate('/tasks')}>
          ← Back to Tasks
        </button>
      </div>
    );
  }

  const isOverdue =
    task.due_date &&
    task.status !== 'completed' &&
    new Date(task.due_date) < new Date().setHours(0, 0, 0, 0);

  const canEdit = checkPermission('tasks.update');
  const canDelete = checkPermission('tasks.delete');
  const canChangeStatus = checkPermission('tasks.change_status') || canEdit;
  const canCreateComment = checkPermission('comments.create');

  return (
    <div className="task-details-container">
      <nav className="task-breadcrumbs">
        <Link to="/tasks" className="crumb-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
          Tasks
        </Link>
        <span className="crumb-sep">/</span>
        {task.project ? (
          <>
            <Link to={`/projects/${task.project.id}`} className="crumb-link">
              {task.project.title}
            </Link>
            <span className="crumb-sep">/</span>
          </>
        ) : null}
        <span className="crumb-active">{task.title}</span>
      </nav>

      <header className="task-header-card">
        <div className="task-header-main">
          <div className="task-header-title-row">
            <h1>{task.title}</h1>
            <div className="task-tags-group">
              <span className={`task-tag priority-${task.priority || 'medium'}`}>
                <span className="tag-dot" />
                {task.priority || 'Medium'} Priority
              </span>
              <span className={`task-tag status-${task.status || 'todo'}`}>
                {task.status?.replace('_', ' ') || 'To Do'}
              </span>
              {isOverdue && (
                <span className="task-tag tag-overdue">
                  ⚠️ Overdue
                </span>
              )}
            </div>
          </div>

          <div className="task-header-actions">
            {canEdit && (
              <button className="task-btn task-btn-edit" onClick={() => setIsEditOpen(true)}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Task
              </button>
            )}
            {canDelete && (
              <button className="task-btn task-btn-delete" onClick={() => setIsDeleteOpen(true)}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Delete
              </button>
            )}
          </div>
        </div>
      </header>

      <div className="task-layout-grid">
        <main className="task-left-column">
          <section className="task-card">
            <div className="task-card-header">
              <div className="card-title-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <h2>Description</h2>
              </div>
            </div>
            <div className="task-description-content">
              {task.description ? (
                <p>{task.description}</p>
              ) : (
                <p className="empty-text">No detailed description provided for this task.</p>
              )}
            </div>
          </section>

          <section className="task-card">
            <div className="task-card-header">
              <div className="card-title-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <h2>Discussion & Comments</h2>
              </div>
              <span className="badge-counter">{task.comments?.length || 0}</span>
            </div>

            <div className="comments-timeline">
              {(!task.comments || task.comments.length === 0) ? (
                <div className="comments-empty-state">
                  <div className="empty-bubble-icon">💬</div>
                  <p>No comments yet. Start the conversation!</p>
                </div>
              ) : (
                task.comments.map((comment) => {
                  const isOwner = user?.id === comment.user_id || user?.id === comment.user?.id;
                  const canDeleteComment =
                    isOwner ||
                    checkPermission('comments.manage_all') ||
                    user?.role === 'admin' ||
                    user?.is_admin;

                  return (
                    <div key={comment.id} className="comment-bubble-item">
                      <div className="comment-avatar">
                        {(comment.user?.name || 'U')[0].toUpperCase()}
                      </div>
                      <div className="comment-bubble-content">
                        <div className="comment-meta-row">
                          <span className="comment-author-name">{comment.user?.name || 'Team Member'}</span>
                          <span className="comment-time">{formatDateTime(comment.created_at)}</span>
                          {canDeleteComment && (
                            <button
                              className="comment-btn-remove"
                              title="Delete comment"
                              onClick={() => setCommentToDelete(comment)}
                            >
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                          )}
                        </div>
                        <div className="comment-text-body">
                          {comment.body}
                        </div>
                      </div>
                    </div>
                  );
                })
              )}
            </div>

            {canCreateComment ? (
              <form onSubmit={handleAddComment} className="task-comment-box">
                {commentError && <div className="task-alert task-alert-danger mb-2">{commentError}</div>}
                <div className="textarea-wrapper">
                  <textarea
                    id="task-comment-input"
                    name="commentBody"
                    rows="3"
                    placeholder="Write a reply, update, or feedback..."
                    value={commentBody}
                    onChange={(e) => setCommentBody(e.target.value)}
                    required
                  />
                </div>
                <div className="comment-box-footer">
                  <button
                    type="submit"
                    className="task-btn task-btn-primary"
                    disabled={commentSubmitting || !commentBody.trim()}
                  >
                    {commentSubmitting ? (
                      <>
                        <span className="btn-spinner" /> Posting...
                      </>
                    ) : (
                      <>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Post Comment
                      </>
                    )}
                  </button>
                </div>
              </form>
            ) : (
              <div className="permission-notice">You don't have permission to add comments.</div>
            )}
          </section>
        </main>

        <aside className="task-right-sidebar">
          <div className="task-card task-card-action">
            <h3 className="sidebar-section-title">Update Status</h3>
            {canChangeStatus ? (
              <div className="status-control-container">
                <div className="custom-select-wrapper">
                  <select
                    id="task-details-status-select"
                    name="status"
                    value={task.status || 'todo'}
                    onChange={(e) => handleStatusChange(e.target.value)}
                    disabled={statusLoading}
                    className={`status-dropdown status-style-${task.status || 'todo'}`}
                  >
                    <option value="todo">To Do</option>
                    <option value="in_progress">In Progress</option>
                    <option value="review">Review</option>
                    <option value="completed">Completed</option>
                  </select>
                  <span className="select-arrow">▼</span>
                </div>
                {statusLoading && (
                  <div className="status-loading-indicator">
                    <span className="mini-spinner" /> Updating status...
                  </div>
                )}
              </div>
            ) : (
              <div className={`status-display-badge status-${task.status || 'todo'}`}>
                {task.status?.replace('_', ' ') || 'To Do'}
              </div>
            )}
          </div>

          <div className="task-card">
            <h3 className="sidebar-section-title">Task Information</h3>
            
            <div className="meta-list">
              <div className="meta-row">
                <div className="meta-key">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                  Project
                </div>
                <div className="meta-val">
                  {task.project ? (
                    <Link to={`/projects/${task.project.id}`} className="project-pill-link">
                      {task.project.title}
                    </Link>
                  ) : (
                    <span className="empty-text">None</span>
                  )}
                </div>
              </div>

              <div className="meta-row">
                <div className="meta-key">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  Assignee
                </div>
                <div className="meta-val">
                  {task.assignee ? (
                    <div className="assignee-tag">
                      <span className="assignee-avatar">
                        {(task.assignee.name || 'U')[0].toUpperCase()}
                      </span>
                      <span className="assignee-name">{task.assignee.name}</span>
                    </div>
                  ) : (
                    <span className="empty-text">Unassigned</span>
                  )}
                </div>
              </div>

              <div className="meta-row">
                <div className="meta-key">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  Due Date
                </div>
                <div className="meta-val">
                  <span className={`date-badge ${isOverdue ? 'date-overdue' : ''}`}>
                    {formatDate(task.due_date)}
                  </span>
                </div>
              </div>

              <div className="meta-row">
                <div className="meta-key">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  Created
                </div>
                <div className="meta-val">
                  <span className="date-badge">{formatDate(task.created_at)}</span>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </div>

      {isEditOpen && (
        <TaskFormModal
          isOpen={isEditOpen}
          onClose={() => setIsEditOpen(false)}
          onSuccess={() => {
            setIsEditOpen(false);
            fetchTaskDetails();
          }}
          task={task}
          projectId={task.project_id || task.project?.id}
        />
      )}

      {isDeleteOpen && (
        <ConfirmModal
          isOpen={isDeleteOpen}
          title="Delete Task"
          message={`Are you sure you want to delete task "${task.title}"? This action cannot be undone.`}
          confirmText="Delete Task"
          danger={true}
          loading={actionLoading}
          onConfirm={handleDeleteTask}
          onClose={() => setIsDeleteOpen(false)}
        />
      )}

      {commentToDelete && (
        <ConfirmModal
          isOpen={Boolean(commentToDelete)}
          title="Delete Comment"
          message="Are you sure you want to delete this comment?"
          confirmText="Delete"
          danger={true}
          loading={deleteCommentLoading}
          onConfirm={handleDeleteComment}
          onClose={() => setCommentToDelete(null)}
        />
      )}
    </div>
  );
}