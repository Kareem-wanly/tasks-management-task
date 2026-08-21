import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import projectsApi from '../../api/projectsApi';
import apiClient from '../../api/apiClient';
import ProjectFormModal from './ProjectFormModal';
import ConfirmModal from './ConfirmModal';
import './ProjectDetailsPage.css';

export default function ProjectDetailsPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { can } = useAuth();

  const [project, setProject] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [editModalOpen, setEditModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [archiveModalOpen, setArchiveModalOpen] = useState(false);
  const [archiveLoading, setArchiveLoading] = useState(false);

  const [activeTab, setActiveTab] = useState('tasks'); 

  const fetchProjectDetails = async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await projectsApi.getById(id);
      const data = res?.data?.data || res?.data || res;
      setProject(data);
    } catch (err) {
      console.error('Failed to load project details:', err);
      setError(err.data?.message || err.message || 'Failed to load project details.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProjectDetails();
  }, [id]);

  const handleConfirmDelete = async () => {
    try {
      setDeleteLoading(true);
      await projectsApi.delete(id);
      navigate('/projects');
    } catch (err) {
      alert(err.data?.message || err.message || 'Failed to delete project.');
    } finally {
      setDeleteLoading(false);
      setDeleteModalOpen(false);
    }
  };

  const handleConfirmArchive = async () => {
    try {
      setArchiveLoading(true);
      if (projectsApi.archive) {
        await projectsApi.archive(id);
      } else {
        await projectsApi.update(id, { status: 'archived' });
      }
      fetchProjectDetails();
    } catch (err) {
      alert(err.data?.message || err.message || 'Failed to archive project.');
    } finally {
      setArchiveLoading(false);
      setArchiveModalOpen(false);
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

  const getStatusBadgeClass = (status) => {
    switch (status?.toLowerCase()) {
      case 'active':
      case 'in_progress':
        return 'status-badge active';
      case 'completed':
        return 'status-badge completed';
      case 'on_hold':
      case 'pending':
        return 'status-badge on-hold';
      case 'archived':
        return 'status-badge archived';
      default:
        return 'status-badge default';
    }
  };

  const getPriorityBadgeClass = (priority) => {
    switch (priority?.toLowerCase()) {
      case 'high':
      case 'urgent':
        return 'priority-badge high';
      case 'medium':
        return 'priority-badge medium';
      case 'low':
        return 'priority-badge low';
      default:
        return 'priority-badge default';
    }
  };

  if (loading) {
    return (
      <div className="project-details-loading">
        <div className="spinner" />
        <span>Loading project details...</span>
      </div>
    );
  }

  if (error || !project) {
    return (
      <div className="project-details-error-card">
        <h2>Error Loading Project</h2>
        <p>{error || 'Project not found.'}</p>
        <button className="btn-secondary" onClick={() => navigate('/projects')}>
          ← Back to Projects
        </button>
      </div>
    );
  }

  const tasks = project.tasks || [];
  const totalTasks = tasks.length;
  const completedTasks = tasks.filter((t) => t.status === 'completed' || t.status === 'done').length;
  const inProgressTasks = tasks.filter((t) => t.status === 'in_progress').length;
  const pendingTasks = tasks.filter((t) => t.status === 'todo' || t.status === 'pending' || !t.status).length;
  const progressPercent = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;

  const members = project.members || [];
  const activities = project.activities || project.activity_logs || [];

  return (
    <div className="project-details-container">
      <div className="project-details-nav">
        <Link to="/projects" className="back-link">
          ← Back to Projects
        </Link>
      </div>

      <div className="project-details-header">
        <div className="project-title-area">
          <div className="title-row">
            <h1>{project.title}</h1>
            <span className={getStatusBadgeClass(project.status)}>
              {project.status?.replace('_', ' ') || 'active'}
            </span>
          </div>
          <p className="project-meta-info">
            Created by <span className="highlight">{project.owner?.name || 'Unknown'}</span> on{' '}
            {formatDate(project.created_at)}
          </p>
        </div>

        <div className="header-actions">
          {can('projects.update') && (
            <button className="btn-edit" onClick={() => setEditModalOpen(true)}>
              Edit Project
            </button>
          )}
          {can('projects.archive') && project.status !== 'archived' && (
            <button className="btn-archive" onClick={() => setArchiveModalOpen(true)}>
              Archive Project
            </button>
          )}
          {can('projects.delete') && (
            <button className="btn-danger-outline" onClick={() => setDeleteModalOpen(true)}>
              Delete Project
            </button>
          )}
        </div>
      </div>

      <div className="project-overview-grid">
        <div className="overview-card info-card">
          <h3>Project Overview</h3>
          <p className="project-description-text">
            {project.description || 'No description provided for this project.'}
          </p>
          <div className="dates-info-grid">
            <div className="date-item">
              <span className="date-label">Start Date</span>
              <span className="date-val">{formatDate(project.start_date)}</span>
            </div>
            <div className="date-item">
              <span className="date-label">Due Date</span>
              <span className="date-val">{formatDate(project.due_date)}</span>
            </div>
            <div className="date-item">
              <span className="date-label">Total Members</span>
              <span className="date-val">{members.length + (project.owner ? 1 : 0)}</span>
            </div>
          </div>
        </div>

        <div className="overview-card stats-card">
          <div className="stats-header">
            <h3>Task Statistics</h3>
            <span className="progress-value">{progressPercent}%</span>
          </div>

          <div className="progress-track">
            <div className="progress-fill" style={{ width: `${progressPercent}%` }} />
          </div>

          <div className="stats-badges-grid">
            <div className="stat-box total">
              <span className="stat-count">{totalTasks}</span>
              <span className="stat-label">Total Tasks</span>
            </div>
            <div className="stat-box completed">
              <span className="stat-count">{completedTasks}</span>
              <span className="stat-label">Completed</span>
            </div>
            <div className="stat-box in-progress">
              <span className="stat-count">{inProgressTasks}</span>
              <span className="stat-label">In Progress</span>
            </div>
            <div className="stat-box pending">
              <span className="stat-count">{pendingTasks}</span>
              <span className="stat-label">Pending</span>
            </div>
          </div>
        </div>
      </div>

      <div className="project-tabs-section">
        <div className="tabs-header">
          <button
            className={`tab-btn ${activeTab === 'tasks' ? 'active' : ''}`}
            onClick={() => setActiveTab('tasks')}
          >
            Tasks ({totalTasks})
          </button>
          <button
            className={`tab-btn ${activeTab === 'members' ? 'active' : ''}`}
            onClick={() => setActiveTab('members')}
          >
            Members ({members.length})
          </button>
          <button
            className={`tab-btn ${activeTab === 'activity' ? 'active' : ''}`}
            onClick={() => setActiveTab('activity')}
          >
            Activity
          </button>
        </div>

        <div className="tab-content">
          {activeTab === 'tasks' && (
            <div className="tasks-tab-view">
              {tasks.length === 0 ? (
                <div className="tab-empty-state">
                  <span className="empty-icon">📝</span>
                  <p>No tasks created for this project yet.</p>
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="project-tasks-table">
                    <thead>
                      <tr>
                        <th>Task Name</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assignee</th>
                        <th>Due Date</th>
                      </tr>
                    </thead>
                    <tbody>
                      {tasks.map((task) => (
                        <tr key={task.id}>
                          <td className="font-semibold">{task.title}</td>
                          <td>
                            <span className={getStatusBadgeClass(task.status)}>
                              {task.status?.replace('_', ' ') || 'pending'}
                            </span>
                          </td>
                          <td>
                            <span className={getPriorityBadgeClass(task.priority)}>
                              {task.priority || 'medium'}
                            </span>
                          </td>
                          <td>{task.assigned_to?.name || task.assignee?.name || 'Unassigned'}</td>
                          <td>{formatDate(task.due_date)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          )}

          {activeTab === 'members' && (
            <div className="members-tab-view">
              <div className="members-grid">
                {project.owner && (
                  <div className="member-card owner-card">
                    <div className="member-avatar">
                      {project.owner.name?.charAt(0).toUpperCase()}
                    </div>
                    <div className="member-info">
                      <span className="member-name">{project.owner.name}</span>
                      <span className="member-email">{project.owner.email}</span>
                      <span className="member-role-badge owner">Project Owner</span>
                    </div>
                  </div>
                )}

                {members.map((member) => (
                  <div key={member.id} className="member-card">
                    <div className="member-avatar">
                      {member.name?.charAt(0).toUpperCase()}
                    </div>
                    <div className="member-info">
                      <span className="member-name">{member.name}</span>
                      <span className="member-email">{member.email}</span>
                      <span className="member-role-badge">Team Member</span>
                    </div>
                  </div>
                ))}

                {members.length === 0 && !project.owner && (
                  <div className="tab-empty-state">
                    <p>No members assigned to this project.</p>
                  </div>
                )}
              </div>
            </div>
          )}

          {activeTab === 'activity' && (
            <div className="activity-tab-view">
              {activities.length === 0 ? (
                <div className="tab-empty-state">
                  <span className="empty-icon">⏱️</span>
                  <p>No recent activity recorded for this project.</p>
                </div>
              ) : (
                <div className="activity-timeline">
                  {activities.map((act, index) => (
                    <div key={act.id || index} className="timeline-item">
                      <div className="timeline-point" />
                      <div className="timeline-body">
                        <div className="timeline-header">
                          <span className="timeline-user">{act.user?.name || act.causer?.name || 'System'}</span>
                          <span className="timeline-date">{formatDateTime(act.created_at)}</span>
                        </div>
                        <p className="timeline-desc">{act.description || act.message || 'Updated project status'}</p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      <ProjectFormModal
        isOpen={editModalOpen}
        onClose={() => setEditModalOpen(false)}
        onSuccess={fetchProjectDetails}
        project={project}
      />

      <ConfirmModal
        isOpen={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        onConfirm={handleConfirmDelete}
        title="Delete Project"
        message={`Are you sure you want to delete "${project.title}"?`}
        confirmText="Delete"
        danger={true}
        loading={deleteLoading}
      />

      <ConfirmModal
        isOpen={archiveModalOpen}
        onClose={() => setArchiveModalOpen(false)}
        onConfirm={handleConfirmArchive}
        title="Archive Project"
        message={`Are you sure you want to archive "${project.title}"?`}
        confirmText="Archive"
        danger={false}
        loading={archiveLoading}
      />
    </div>
  );
}