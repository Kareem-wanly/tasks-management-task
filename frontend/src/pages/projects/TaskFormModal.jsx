import { useState, useEffect } from 'react';
import tasksApi from '../../api/tasksApi';

export default function TaskFormModal({ isOpen, onClose, onSuccess, projectId, members = [] }) {
  const initialForm = {
    title: '',
    description: '',
    project_id: projectId,
    assigned_to_id: '',
    status: 'todo', 
    priority: 'medium',
    due_date: '',
  };

  const [formData, setFormData] = useState(initialForm);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState(null);

  useEffect(() => {
    if (isOpen) {
      setFormData({
        ...initialForm,
        project_id: projectId,
      });
      setErrors({});
      setServerError(null);
    }
  }, [isOpen, projectId]);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    
    if (errors[name]) {
      setErrors((prev) => {
        const updated = { ...prev };
        delete updated[name];
        return updated;
      });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setErrors({});
    setServerError(null);

    const payload = {
      title: formData.title,
      description: formData.description,
      status: formData.status, 
      priority: formData.priority,
      due_date: formData.due_date || null,
      assigned_to_id: formData.assigned_to_id ? Number(formData.assigned_to_id) : null,
      assigned_to: formData.assigned_to_id ? Number(formData.assigned_to_id) : null,
    };

    try {
      await tasksApi.create(projectId, payload);
      onSuccess(); 
      onClose();   
    } catch (err) {
      console.error('Failed to create task:', err);
      if (err.data?.errors) {
        setErrors(err.data.errors);
      } else {
        setServerError(err.data?.message || err.message || 'Failed to create task.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const uniqueMembers = Array.from(
    new Map((members || []).map((m) => [m.id, m])).values()
  );

  if (!isOpen) return null;

  return (
    <div className="modal-overlay" onClick={onClose}>
    <div className="modal-content" onClick={(e) => e.stopPropagation()}>
      <div className="modal-header">
        <h3>Create New Task</h3>
        <button className="modal-close-btn" onClick={onClose} disabled={submitting}>
            ✕
          </button>
        </div>

        {serverError && <div className="modal-error-banner">{serverError}</div>}

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label htmlFor="task-title">
              Task Title <span className="req">*</span>
            </label>
            <input
              id="task-title"
              type="text"
              name="title"
              value={formData.title}
              onChange={handleChange}
              placeholder="e.g. Implement Authentication"
              disabled={submitting}
              className={`form-control ${errors.title ? 'is-invalid' : ''}`}
              required
            />
            {errors.title && <span className="error-text">{errors.title[0]}</span>}
          </div>

          <div className="form-group">
            <label htmlFor="task-desc">Description</label>
            <textarea
              id="task-desc"
              name="description"
              value={formData.description}
              onChange={handleChange}
              placeholder="Describe the task details..."
              rows={3}
              disabled={submitting}
              className={`form-control ${errors.description ? 'is-invalid' : ''}`}
            />
            {errors.description && <span className="error-text">{errors.description[0]}</span>}
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="task-status">Status</label>
              <select
                id="task-status"
                name="status"
                value={formData.status}
                onChange={handleChange}
                disabled={submitting}
                className="form-control"
              >
                <option value="todo">To Do</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="task-priority">Priority</label>
              <select
                id="task-priority"
                name="priority"
                value={formData.priority}
                onChange={handleChange}
                disabled={submitting}
                className="form-control"
              >
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="task-assignee">Assign To</label>
              <select
                id="task-assignee"
                name="assigned_to_id"
                value={formData.assigned_to_id}
                onChange={handleChange}
                disabled={submitting}
                className="form-control"
              >
                <option value="">Unassigned</option>
                {uniqueMembers.map((member) => (
                  <option key={member.id} value={member.id}>
                    {member.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="task-due-date">Due Date</label>
              <input
                id="task-due-date"
                type="date"
                name="due_date"
                value={formData.due_date}
                onChange={handleChange}
                disabled={submitting}
                className={`form-control ${errors.due_date ? 'is-invalid' : ''}`}
              />
              {errors.due_date && <span className="error-text">{errors.due_date[0]}</span>}
            </div>
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
              disabled={submitting}
            >
              {submitting ? 'Creating Task...' : 'Create Task'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}