import { useState, useEffect } from 'react';
import tasksApi from '../../api/tasksApi';
import projectsApi from '../../api/projectsApi';

export default function TaskFormModal({ isOpen, onClose, onSuccess, projectId = null }) {
  const [projects, setProjects] = useState([]);
  const [members, setMembers] = useState([]);
  const [loadingProjects, setLoadingProjects] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});

  const [formData, setFormData] = useState({
    project_id: projectId || '',
    title: '',
    description: '',
    status: 'todo',
    priority: 'medium',
    assigned_to: '',
    due_date: '',
  });

  useEffect(() => {
    if (!isOpen) return;

    setFormData({
      project_id: projectId || '',
      title: '',
      description: '',
      status: 'todo',
      priority: 'medium',
      assigned_to: '',
      due_date: '',
    });
    setErrors({});

    if (!projectId) {
      setLoadingProjects(true);
      projectsApi
        .getAll({ per_page: 100 })
        .then((res) => {
          const list = res.data?.data || res.data || [];
          setProjects(Array.isArray(list) ? list : []);
        })
        .catch((err) => console.error('Failed to load projects:', err))
        .finally(() => setLoadingProjects(false));
    }
  }, [isOpen, projectId]);

  useEffect(() => {
    const activeProjectId = projectId || formData.project_id;
    if (!activeProjectId) {
      setMembers([]);
      return;
    }

    projectsApi
      .getById(activeProjectId)
      .then((res) => {
        const proj = res.data?.data || res.data;
        const allMembers = [];
        if (proj?.owner) allMembers.push(proj.owner);
        if (Array.isArray(proj?.members)) {
          proj.members.forEach((m) => {
            if (!allMembers.some((existing) => existing.id === m.id)) {
              allMembers.push(m);
            }
          });
        }
        setMembers(allMembers);
      })
      .catch((err) => console.error('Failed to load project members:', err));
  }, [formData.project_id, projectId]);

  if (!isOpen) return null;

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: null }));
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setErrors({});

    const activeProjectId = projectId || formData.project_id;

    if (!activeProjectId) {
      setErrors({ project_id: ['Please select a project for this task.'] });
      setSubmitting(false);
      return;
    }

    try {
      const payload = {
        title: formData.title,
        description: formData.description || null,
        status: formData.status,
        priority: formData.priority,
        assigned_to: formData.assigned_to ? Number(formData.assigned_to) : null,
        due_date: formData.due_date || null,
      };

      if (typeof tasksApi.createForProject === 'function') {
        await tasksApi.createForProject(activeProjectId, payload);
      } else {
        await tasksApi.create(activeProjectId, payload);
      }

      onSuccess();
    } catch (err) {
      console.error('Task creation error:', err);
      if (err.status === 422 && err.data?.errors) {
        setErrors(err.data.errors);
      } else {
        setErrors({ general: err.data?.message || 'Failed to create task.' });
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '520px' }}>
        <div className="modal-header">
          <h3>Create New Task</h3>
          <button className="modal-close-btn" onClick={onClose} disabled={submitting}>
            ✕
          </button>
        </div>

        {errors.general && (
          <div className="alert alert-danger" style={{ marginBottom: '1rem' }}>
            {errors.general}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          {!projectId && (
            <div className="form-group" style={{ marginBottom: '1rem' }}>
              <label htmlFor="project_id" style={{ display: 'block', marginBottom: '0.35rem', fontWeight: '500' }}>
                Project *
              </label>
              <select
                id="project_id"
                name="project_id"
                value={formData.project_id}
                onChange={handleChange}
                required
                disabled={submitting || loadingProjects}
                className="form-control"
              >
                <option value="">{loadingProjects ? 'Loading projects...' : '-- Select Project --'}</option>
                {projects.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.title || p.name}
                  </option>
                ))}
              </select>
              {errors.project_id && <span className="error-text" style={{ color: '#dc2626', fontSize: '0.8rem' }}>{errors.project_id[0]}</span>}
            </div>
          )}

          <div className="form-group" style={{ marginBottom: '1rem' }}>
            <label htmlFor="title" style={{ display: 'block', marginBottom: '0.35rem', fontWeight: '500' }}>
              Task Title *
            </label>
            <input
              type="text"
              id="title"
              name="title"
              value={formData.title}
              onChange={handleChange}
              placeholder="e.g. Integrate Payment Gateway"
              required
              disabled={submitting}
              className="form-control"
            />
            {errors.title && <span className="error-text" style={{ color: '#dc2626', fontSize: '0.8rem' }}>{errors.title[0]}</span>}
          </div>

          <div className="form-group" style={{ marginBottom: '1rem' }}>
            <label htmlFor="description" style={{ display: 'block', marginBottom: '0.35rem', fontWeight: '500' }}>
              Description
            </label>
            <textarea
              id="description"
              name="description"
              value={formData.description}
              onChange={handleChange}
              rows="3"
              placeholder="Task details and scope..."
              disabled={submitting}
              className="form-control"
            />
            {errors.description && <span className="error-text" style={{ color: '#dc2626', fontSize: '0.8rem' }}>{errors.description[0]}</span>}
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', marginBottom: '1rem' }}>
            <div className="form-group">
              <label htmlFor="status" style={{ display: 'block', marginBottom: '0.35rem', fontWeight: '500' }}>
                Status
              </label>
              <select
                id="status"
                name="status"
                value={formData.status}
                onChange={handleChange}
                disabled={submitting}
                className="form-control"
              >
                <option value="todo">To Do</option>
                <option value="in_progress">In Progress</option>
                <option value="review">Review</option>
                <option value="completed">Completed</option>
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="priority" style={{ display: 'block', marginBottom: '0.35rem', fontWeight: '500' }}>
                Priority
              </label>
              <select
                id="priority"
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

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem', marginBottom: '1.5rem' }}>
            <div className="form-group">
              <label htmlFor="assigned_to" style={{ display: 'block', marginBottom: '0.35rem', fontWeight: '500' }}>
                Assignee
              </label>
              <select
                id="assigned_to"
                name="assigned_to"
                value={formData.assigned_to}
                onChange={handleChange}
                disabled={submitting || (!projectId && !formData.project_id)}
                className="form-control"
              >
                <option value="">Unassigned</option>
                {members.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.name} ({m.email})
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label htmlFor="due_date" style={{ display: 'block', marginBottom: '0.35rem', fontWeight: '500' }}>
                Due Date
              </label>
              <input
                type="date"
                id="due_date"
                name="due_date"
                value={formData.due_date}
                onChange={handleChange}
                disabled={submitting}
                className="form-control"
              />
              {errors.due_date && <span className="error-text" style={{ color: '#dc2626', fontSize: '0.8rem' }}>{errors.due_date[0]}</span>}
            </div>
          </div>

          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.75rem' }}>
            <button type="button" className="btn-secondary" onClick={onClose} disabled={submitting}>
              Cancel
            </button>
            <button type="submit" className="btn-primary" disabled={submitting}>
              {submitting ? 'Creating...' : 'Create Task'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}