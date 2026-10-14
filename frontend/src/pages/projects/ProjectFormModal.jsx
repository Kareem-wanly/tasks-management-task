import { useState, useEffect } from 'react';
import projectsApi from '../../api/projectsApi';

export default function ProjectFormModal({ isOpen, onClose, onSuccess, project = null }) {
  const isEditMode = Boolean(project);

  const [formData, setFormData] = useState({
    title: '',
    description: '',
    status: 'active',
    start_date: '',
    due_date: '',
  });

  const [errors, setErrors] = useState({});
  const [generalError, setGeneralError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (project) {
      setFormData({
        title: project.title || '',
        description: project.description || '',
        status: project.status || 'active',
        start_date: project.start_date ? project.start_date.split('T')[0] : '',
        due_date: project.due_date ? project.due_date.split('T')[0] : '',
      });
    } else {
      setFormData({
        title: '',
        description: '',
        status: 'active',
        start_date: '',
        due_date: '',
      });
    }
    setErrors({});
    setGeneralError('');
  }, [project, isOpen]);

  if (!isOpen) return null;

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors((prev) => {
        const next = { ...prev };
        delete next[name];
        return next;
      });
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});
    setGeneralError('');
    setSubmitting(true);

    try {
      if (isEditMode) {
        await projectsApi.update(project.id, formData);
      } else {
        await projectsApi.create(formData);
      }
      onSuccess();
      onClose();
    } catch (err) {
      if (err.data?.errors) {
        setErrors(err.data.errors);
      } else if (err.data?.message) {
        setGeneralError(err.data.message);
      } else {
        setGeneralError(err.message || 'An unexpected error occurred. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h2>{isEditMode ? 'Edit Project' : 'Create New Project'}</h2>
          <button className="modal-close-btn" onClick={onClose} aria-label="Close">
            ×
          </button>
        </div>

        {generalError && <div className="modal-error-banner">{generalError}</div>}

        <form onSubmit={handleSubmit} className="modal-form">
          <div className="form-group">
            <label htmlFor="title">
              Project Title <span className="req">*</span>
            </label>
            <input
              id="title"
              type="text"
              name="title"
              value={formData.title}
              onChange={handleChange}
              placeholder="e.g. Website Redesign"
              className={errors.title ? 'input-error' : ''}
              disabled={submitting}
              autoFocus
            />
            {errors.title && <span className="field-error-text">{errors.title[0]}</span>}
          </div>

          <div className="form-group">
            <label htmlFor="description">Description</label>
            <textarea
              id="description"
              name="description"
              rows={3}
              value={formData.description}
              onChange={handleChange}
              placeholder="Brief description of the project goals..."
              className={errors.description ? 'input-error' : ''}
              disabled={submitting}
            />
            {errors.description && (
              <span className="field-error-text">{errors.description[0]}</span>
            )}
          </div>

          <div className="form-row">
            <div className="form-group">
              <label htmlFor="status">Status</label>
              <select
                id="status"
                name="status"
                value={formData.status}
                onChange={handleChange}
                disabled={submitting}
              >
                <option value="active">Active</option>
                <option value="in_progress">In Progress</option>
                <option value="on_hold">On Hold</option>
                <option value="completed">Completed</option>
              </select>
              {errors.status && <span className="field-error-text">{errors.status[0]}</span>}
            </div>

            <div className="form-group">
              <label htmlFor="start_date">Start Date</label>
              <input
                id="start_date"
                type="date"
                name="start_date"
                value={formData.start_date}
                onChange={handleChange}
                className={errors.start_date ? 'input-error' : ''}
                disabled={submitting}
              />
              {errors.start_date && (
                <span className="field-error-text">{errors.start_date[0]}</span>
              )}
            </div>

            <div className="form-group">
              <label htmlFor="due_date">Due Date</label>
              <input
                id="due_date"
                type="date"
                name="due_date"
                value={formData.due_date}
                onChange={handleChange}
                className={errors.due_date ? 'input-error' : ''}
                disabled={submitting}
              />
              {errors.due_date && <span className="field-error-text">{errors.due_date[0]}</span>}
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
            <button type="submit" className="btn-primary" disabled={submitting}>
              {submitting ? 'Saving...' : isEditMode ? 'Save Changes' : 'Create Project'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}