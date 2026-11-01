import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import projectsApi from '../../api/projectsApi';
import tasksApi from '../../api/tasksApi';
import activitiesApi from '../../api/activitiesApi';
import ProjectFormModal from './ProjectFormModal';
import ConfirmModal from './ConfirmModal';
import AddMemberModal from './AddMemberModal';
import TaskFormModal from './TaskFormModal';
import './ProjectDetailsPage.css';

export default function ProjectDetailsPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { can } = useAuth();
  const { showToast } = useToast();

  const [project, setProject] = useState(null);
  const [tasksPage, setTasksPage] = useState(1);
  const [tasksData, setTasksData] = useState({ items: [], lastPage: 1, total: 0 });
  const [tasksLoading, setTasksLoading] = useState(true);
  const [tasksError, setTasksError] = useState(null);
  const [tasksReloadKey, setTasksReloadKey] = useState(0);
  const [activitiesPage, setActivitiesPage] = useState(1);
  const [activitiesData, setActivitiesData] = useState({ items: [], lastPage: 1, total: 0 });
  const [activitiesLoading, setActivitiesLoading] = useState(false);
  const [activitiesError, setActivitiesError] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [editModalOpen, setEditModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [deleteLoading, setDeleteLoading] = useState(false);
  const [archiveModalOpen, setArchiveModalOpen] = useState(false);
  const [archiveLoading, setArchiveLoading] = useState(false);

  const [addMemberModalOpen, setAddMemberModalOpen] = useState(false);
  const [memberToRemove, setMemberToRemove] = useState(null);
  const [removeMemberLoading, setRemoveMemberLoading] = useState(false);

  const [createTaskModalOpen, setCreateTaskModalOpen] = useState(false);

  const [activeTab, setActiveTab] = useState('tasks');

  const fetchProjectDetails = useCallback(async (signal) => {
    try {
      setLoading(true);
      setError(null);
      setProject(await projectsApi.getById(id, { signal }));
    } catch (err) {
      if (err.name === 'AbortError') return;
      console.error('Failed to load project details:', err);
      setError(err.data?.message || err.message || 'Failed to load project details.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    const controller = new AbortController();
    fetchProjectDetails(controller.signal);

    return () => {
      controller.abort();
    };
  }, [fetchProjectDetails]);

  // GET /projects/{id}/tasks is paginated; load one page at a time instead of treating page 1 as everything
  useEffect(() => {
    const controller = new AbortController();
    setTasksLoading(true);
    setTasksError(null);
    tasksApi
      .getByProject(id, { page: tasksPage }, { signal: controller.signal })
      .then(setTasksData)
      .catch((err) => {
        if (err.name === 'AbortError') return;
        setTasksError(err.data?.message || err.message || 'Failed to load project tasks.');
      })
      .finally(() => {
        if (!controller.signal.aborted) setTasksLoading(false);
      });

    return () => controller.abort();
  }, [id, tasksPage, tasksReloadKey]);

  useEffect(() => {
    if (activeTab !== 'activity') return;
    const controller = new AbortController();
    setActivitiesLoading(true);
    setActivitiesError(null);
    activitiesApi
      .getByProject(id, { page: activitiesPage }, { signal: controller.signal })
      .then(setActivitiesData)
      .catch((err) => {
        if (err.name === 'AbortError') return;
        setActivitiesError(err.data?.message || err.message || 'Failed to load project activity.');
      })
      .finally(() => {
        if (!controller.signal.aborted) setActivitiesLoading(false);
      });

    return () => controller.abort();
  }, [id, activeTab, activitiesPage]);

  const reloadProjectAndTasks = () => {
    fetchProjectDetails();
    setTasksReloadKey((k) => k + 1);
  };

  const handleConfirmDelete = async () => {
    try {
      setDeleteLoading(true);
      await projectsApi.delete(id);
      showToast('Project deleted successfully.', 'success');
      navigate('/projects');
    } catch (err) {
      showToast(err.data?.message || err.message || 'Failed to delete project.', 'error');
    } finally {
      setDeleteLoading(false);
      setDeleteModalOpen(false);
    }
  };

  const handleConfirmArchive = async () => {
    try {
      setArchiveLoading(true);
      const isArchived = project?.status === 'archived';
      await projectsApi.archive(id);
      showToast(`Project ${isArchived ? 'unarchived' : 'archived'} successfully.`, 'success');
      setProject((prev) => ({
        ...prev,
        status: isArchived ? 'active' : 'archived',
      }));
      fetchProjectDetails();
    } catch (err) {
      showToast(err.data?.message || err.message || 'Failed to update project archive status.', 'error');
    } finally {
      setArchiveLoading(false);
      setArchiveModalOpen(false);
    }
  };

  const handleConfirmRemoveMember = async () => {
    if (!memberToRemove) return;
    try {
      setRemoveMemberLoading(true);
      await projectsApi.removeMember(project.id, memberToRemove.id);
      showToast(`Member "${memberToRemove.name}" removed successfully.`, 'success');
      setProject((prev) => ({
        ...prev,
        members: (prev.members || []).filter((m) => m.id !== memberToRemove.id),
      }));
      setMemberToRemove(null);
    } catch (err) {
      showToast(err.data?.message || err.message || 'Failed to remove member from project.', 'error');
    } finally {
      setRemoveMemberLoading(false);
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
      case 'done':
        return 'status-badge completed';
      case 'on_hold':
      case 'pending':
      case 'todo':
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

  const tasks = tasksData.items;
  // Counts come from the server so they cover every task, not just the loaded page
  const totalTasks = project.tasks_count ?? tasksData.total;
  const completedTasks = project.completed_tasks_count ?? 0;
  const inProgressTasks = project.in_progress_tasks_count ?? 0;
  const pendingTasks = Math.max(totalTasks - completedTasks - inProgressTasks, 0);
  const progressPercent = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;

  const members = (project.members || []).filter((m) => m.id !== project.owner?.id);
  const allProjectUsers = project.owner ? [project.owner, ...members] : members;
  
  const activities = activitiesData.items;
  const canManageMembers = can('projects.manage_members');
  const canCreateTasks = can('tasks.create');
  const isArchived = project.status === 'archived';

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
          {can('projects.archive') && (
            <button className="btn-archive" onClick={() => setArchiveModalOpen(true)}>
              {isArchived ? 'Unarchive Project' : 'Archive Project'}
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
              <span className="date-val">{allProjectUsers.length}</span>
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
            Members ({allProjectUsers.length})
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
              <div className="tab-actions-bar">
                <h4>Project Tasks</h4>
                {canCreateTasks && (
                  <button className="btn-primary btn-sm" onClick={() => setCreateTaskModalOpen(true)}>
                    + Create Task
                  </button>
                )}
              </div>

              {tasksLoading ? (
                <div className="tab-empty-state">
                  <p>Loading tasks...</p>
                </div>
              ) : tasksError ? (
                <div className="tab-empty-state">
                  <p>{tasksError}</p>
                  <button className="btn-secondary btn-sm" onClick={() => setTasksReloadKey((k) => k + 1)}>
                    Try Again
                  </button>
                </div>
              ) : tasks.length === 0 ? (
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
                      {tasks.map((task) => {
                        const assigneeName =
                          task.assignee?.name ||
                          'Unassigned';

                        return (
                          <tr key={task.id}>
                            <td className="font-semibold">
                              <Link to={`/tasks/${task.id}`} className="task-title-link">
                                {task.title}
                              </Link>
                            </td>
                            <td>
                              <span className={getStatusBadgeClass(task.status)}>
                                {task.status?.replace('_', ' ') || 'todo'}
                              </span>
                            </td>
                            <td>
                              <span className={getPriorityBadgeClass(task.priority)}>
                                {task.priority || 'medium'}
                              </span>
                            </td>
                            <td>{assigneeName}</td>
                            <td>{formatDate(task.due_date)}</td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                  <Pager page={tasksPage} lastPage={tasksData.lastPage} onChange={setTasksPage} />
                </div>
              )}
            </div>
          )}

          {activeTab === 'members' && (
            <div className="members-tab-view">
              <div className="tab-actions-bar">
                <h4>Team Members</h4>
                {canManageMembers && (
                  <button className="btn-primary btn-sm" onClick={() => setAddMemberModalOpen(true)}>
                    + Add Member
                  </button>
                )}
              </div>

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

                    {canManageMembers && (
                      <button
                        className="btn-member-remove"
                        title="Remove member from project"
                        onClick={() => setMemberToRemove(member)}
                      >
                        ✕
                      </button>
                    )}
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
              {activitiesLoading ? (
                <div className="tab-empty-state">
                  <p>Loading activity...</p>
                </div>
              ) : activitiesError ? (
                <div className="tab-empty-state">
                  <p>{activitiesError}</p>
                </div>
              ) : activities.length === 0 ? (
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
                  <Pager page={activitiesPage} lastPage={activitiesData.lastPage} onChange={setActivitiesPage} />
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      <ProjectFormModal
        isOpen={editModalOpen}
        onClose={() => setEditModalOpen(false)}
        onSuccess={() => fetchProjectDetails()}
        project={project}
      />

      <AddMemberModal
        isOpen={addMemberModalOpen}
        onClose={() => setAddMemberModalOpen(false)}
        onSuccess={() => fetchProjectDetails()}
        projectId={project.id}
        existingMembers={members}
        ownerId={project.owner?.id}
      />

      <TaskFormModal
        isOpen={createTaskModalOpen}
        onClose={() => setCreateTaskModalOpen(false)}
        onSuccess={reloadProjectAndTasks}
        projectId={project.id}
      />

      <ConfirmModal
        isOpen={!!memberToRemove}
        onClose={() => setMemberToRemove(null)}
        onConfirm={handleConfirmRemoveMember}
        title="Remove Team Member"
        message={`Are you sure you want to remove ${memberToRemove?.name} from this project?`}
        confirmText="Remove Member"
        danger={true}
        loading={removeMemberLoading}
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
        title={isArchived ? 'Unarchive Project' : 'Archive Project'}
        message={`Are you sure you want to ${isArchived ? 'unarchive' : 'archive'} "${project.title}"?`}
        confirmText={isArchived ? 'Unarchive' : 'Archive'}
        danger={false}
        loading={archiveLoading}
      />
    </div>
  );
}

function Pager({ page, lastPage, onChange }) {
  if (lastPage <= 1) return null;

  return (
    <div className="tab-actions-bar" style={{ justifyContent: 'flex-end', gap: '8px', marginTop: '12px' }}>
      <button className="btn-secondary btn-sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>
        Previous
      </button>
      <span>
        Page {page} of {lastPage}
      </span>
      <button className="btn-secondary btn-sm" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>
        Next
      </button>
    </div>
  );
}