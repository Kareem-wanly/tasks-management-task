import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import projectsApi from '../../api/projectsApi';
import apiClient from '../../api/apiClient';
import ProjectFormModal from './ProjectFormModal';
import ConfirmModal from './ConfirmModal';
import './ProjectsPage.css';

export default function ProjectsPage() {
  const { user, can } = useAuth() || {};
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();

  const checkPermission = (perm) => {
    if (typeof can === 'function') return can(perm);
    if (user?.role === 'admin' || user?.is_admin || user?.role?.name === 'admin') return true;
    if (Array.isArray(user?.permissions)) {
      return user.permissions.includes(perm) || user.permissions.some((p) => (p.name || p) === perm);
    }
    return false;
  };

  const currentSearch = searchParams.get('search') || '';
  const currentStatus = searchParams.get('status') || '';
  const currentMember = searchParams.get('member_id') || '';
  const currentSort = searchParams.get('sort') || 'created_at';
  const currentDirection = searchParams.get('direction') || 'desc';
  const currentPage = parseInt(searchParams.get('page') || '1', 10);

  const [projects, setProjects] = useState([]);
  const [members, setMembers] = useState([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0, per_page: 10 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [viewMode, setViewMode] = useState('table');
  const [searchInput, setSearchInput] = useState(currentSearch);

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [selectedProject, setSelectedProject] = useState(null);

  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [projectToDelete, setProjectToDelete] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const [archiveModalOpen, setArchiveModalOpen] = useState(false);
  const [projectToArchive, setProjectToArchive] = useState(null);
  const [archiveLoading, setArchiveLoading] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      if (searchInput !== currentSearch) {
        updateFilter('search', searchInput);
      }
    }, 400);

    return () => clearTimeout(timer);
  }, [searchInput]);

  useEffect(() => {
    const canViewUsers = checkPermission('users.view') || checkPermission('projects.create');
    if (!canViewUsers) return;

    let isMounted = true;
    async function fetchUsers() {
      try {
        const res = await apiClient.get('/users?per_page=100');
        const usersData = res?.data?.data || res?.data || res || [];
        if (isMounted && Array.isArray(usersData)) {
          setMembers(usersData);
        }
      } catch (err) {
      }
    }
    fetchUsers();

    return () => {
      isMounted = false;
    };
  }, [user]);

  const fetchProjects = async () => {
    try {
      setLoading(true);
      setError(null);

      const params = {
        page: currentPage,
        per_page: 10,
        sort: currentSort,
        direction: currentDirection,
      };

      if (currentSearch) params.search = currentSearch;
      if (currentStatus) params.status = currentStatus;
      if (currentMember) params.member_id = currentMember;

      const res = await projectsApi.getAll(params);

      const projectList = res?.data?.data || res?.data || (Array.isArray(res) ? res : []);
      const paginationMeta = res?.data?.meta || res?.meta || {
        current_page: currentPage,
        last_page: 1,
        total: Array.isArray(projectList) ? projectList.length : 0,
        per_page: 10,
      };

      setProjects(Array.isArray(projectList) ? projectList : []);
      setMeta(paginationMeta);
    } catch (err) {
      console.error('Failed to load projects:', err);
      setError('Failed to load projects. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProjects();
  }, [searchParams]);

  const updateFilter = (key, value) => {
    const newParams = new URLSearchParams(searchParams);
    if (value) {
      newParams.set(key, value);
    } else {
      newParams.delete(key);
    }
    if (key !== 'page') {
      newParams.set('page', '1');
    }
    setSearchParams(newParams);
  };

  const handleSortChange = (sortBy) => {
    const newDirection = currentSort === sortBy && currentDirection === 'asc' ? 'desc' : 'asc';
    const newParams = new URLSearchParams(searchParams);
    newParams.set('sort', sortBy);
    newParams.set('direction', newDirection);
    newParams.set('page', '1');
    setSearchParams(newParams);
  };

  const handleOpenCreate = () => {
    setSelectedProject(null);
    setFormModalOpen(true);
  };

  const handleOpenEdit = (project, e) => {
    e?.stopPropagation();
    setSelectedProject(project);
    setFormModalOpen(true);
  };

  const handleOpenDelete = (project, e) => {
    e?.stopPropagation();
    setProjectToDelete(project);
    setDeleteModalOpen(true);
  };

  const handleConfirmDelete = async () => {
    if (!projectToDelete) return;
    try {
      setDeleteLoading(true);
      await projectsApi.delete(projectToDelete.id);
      setDeleteModalOpen(false);
      setProjectToDelete(null);
      fetchProjects();
    } catch (err) {
      alert(err.data?.message || err.message || 'Failed to delete project.');
    } finally {
      setDeleteLoading(false);
    }
  };

  const handleOpenArchive = (project, e) => {
    e?.stopPropagation();
    setProjectToArchive(project);
    setArchiveModalOpen(true);
  };

  const handleConfirmArchive = async () => {
    if (!projectToArchive) return;
    try {
      setArchiveLoading(true);
      if (projectsApi.archive) {
        await projectsApi.archive(projectToArchive.id);
      } else {
        await projectsApi.update(projectToArchive.id, { status: 'archived' });
      }
      setArchiveModalOpen(false);
      setProjectToArchive(null);
      fetchProjects();
    } catch (err) {
      alert(err.data?.message || err.message || 'Failed to archive project.');
    } finally {
      setArchiveLoading(false);
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

  return (
    <div className="projects-container">
      <div className="projects-header">
        <div>
          <h1>Projects</h1>
          <p>Manage, track, and collaborate on your team projects.</p>
        </div>
        {checkPermission('projects.create') && (
          <button className="btn-primary" onClick={handleOpenCreate}>
            + Create Project
          </button>
        )}
      </div>

      <div className="filters-toolbar">
        <div className="search-box">
          <input
            type="text"
            id="projects-search-input"
            name="search"
            placeholder="Search projects by title or description..."
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
          />
          {searchInput && (
            <button className="clear-btn" onClick={() => setSearchInput('')}>
              ×
            </button>
          )}
        </div>

        <div className="filters-group">
          <select
            id="projects-status-filter"
            name="status"
            value={currentStatus}
            onChange={(e) => updateFilter('status', e.target.value)}
            className="filter-select"
          >
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="on_hold">On Hold</option>
            <option value="archived">Archived</option>
          </select>

          {members.length > 0 && (
            <select
              id="projects-member-filter"
              name="member_id"
              value={currentMember}
              onChange={(e) => updateFilter('member_id', e.target.value)}
              className="filter-select"
            >
              <option value="">All Members</option>
              {members.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.name}
                </option>
              ))}
            </select>
          )}

          <select
            id="projects-sort-filter"
            name="sort"
            value={currentSort}
            onChange={(e) => updateFilter('sort', e.target.value)}
            className="filter-select"
          >
            <option value="created_at">Sort: Created Date</option>
            <option value="start_date">Sort: Start Date</option>
            <option value="due_date">Sort: Due Date</option>
            <option value="title">Sort: Project Title</option>
          </select>

          <button
            className="btn-secondary sort-dir-btn"
            onClick={() => updateFilter('direction', currentDirection === 'asc' ? 'desc' : 'asc')}
            title={`Direction: ${currentDirection.toUpperCase()}`}
          >
            {currentDirection === 'asc' ? '↑ ASC' : '↓ DESC'}
          </button>

          <div className="view-toggle">
            <button
              className={`toggle-btn ${viewMode === 'table' ? 'active' : ''}`}
              onClick={() => setViewMode('table')}
            >
              Table
            </button>
            <button
              className={`toggle-btn ${viewMode === 'cards' ? 'active' : ''}`}
              onClick={() => setViewMode('cards')}
            >
              Cards
            </button>
          </div>
        </div>
      </div>

      {loading ? (
        <div className="projects-loading">
          <div className="spinner" />
          <span>Loading projects...</span>
        </div>
      ) : error ? (
        <div className="projects-error">{error}</div>
      ) : projects.length === 0 ? (
        <div className="projects-empty">
          <div className="empty-icon">📁</div>
          <h3>No projects found</h3>
          <p>Try adjusting your search query or status filter.</p>
        </div>
      ) : viewMode === 'table' ? (
        <div className="table-responsive">
          <table className="projects-table">
            <thead>
              <tr>
                <th onClick={() => handleSortChange('title')} className="sortable-th">
                  Project Name {currentSort === 'title' && (currentDirection === 'asc' ? '▲' : '▼')}
                </th>
                <th>Status</th>
                <th onClick={() => handleSortChange('start_date')} className="sortable-th">
                  Start Date {currentSort === 'start_date' && (currentDirection === 'asc' ? '▲' : '▼')}
                </th>
                <th onClick={() => handleSortChange('due_date')} className="sortable-th">
                  Due Date {currentSort === 'due_date' && (currentDirection === 'asc' ? '▲' : '▼')}
                </th>
                <th>Owner & Team</th>
                <th className="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {projects.map((project) => (
                <tr
                  key={project.id}
                  onClick={() => navigate(`/projects/${project.id}`)}
                  className="clickable-row"
                >
                  <td className="font-semibold text-primary">{project.title}</td>
                  <td>
                    <span className={getStatusBadgeClass(project.status)}>
                      {project.status?.replace('_', ' ') || 'active'}
                    </span>
                  </td>
                  <td>{formatDate(project.start_date)}</td>
                  <td>{formatDate(project.due_date)}</td>
                  <td>
                    <div className="owner-badge">
                      <span className="owner-name">{project.owner?.name || 'Unassigned'}</span>
                      {project.members?.length > 0 && (
                        <span className="members-count">+{project.members.length} members</span>
                      )}
                    </div>
                  </td>
                  <td className="text-right" onClick={(e) => e.stopPropagation()}>
                    <div className="actions-cell">
                      {checkPermission('projects.update') && (
                        <button
                          className="btn-action edit"
                          onClick={(e) => handleOpenEdit(project, e)}
                          title="Edit Project"
                        >
                          Edit
                        </button>
                      )}
                      {checkPermission('projects.archive') && project.status !== 'archived' && (
                        <button
                          className="btn-action archive"
                          onClick={(e) => handleOpenArchive(project, e)}
                          title="Archive Project"
                        >
                          Archive
                        </button>
                      )}
                      {checkPermission('projects.delete') && (
                        <button
                          className="btn-action delete"
                          onClick={(e) => handleOpenDelete(project, e)}
                          title="Delete Project"
                        >
                          Delete
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="projects-cards-grid">
          {projects.map((project) => (
            <div
              key={project.id}
              className="project-card"
              onClick={() => navigate(`/projects/${project.id}`)}
            >
              <div className="project-card-header">
                <h3>{project.title}</h3>
                <span className={getStatusBadgeClass(project.status)}>
                  {project.status?.replace('_', ' ') || 'active'}
                </span>
              </div>
              <p className="project-card-desc">
                {project.description || 'No description provided for this project.'}
              </p>
              <div className="project-card-dates">
                <div>
                  <span className="date-label">Start:</span> {formatDate(project.start_date)}
                </div>
                <div>
                  <span className="date-label">Due:</span> {formatDate(project.due_date)}
                </div>
              </div>
              <div className="project-card-footer" onClick={(e) => e.stopPropagation()}>
                <span className="owner-tag">Owner: {project.owner?.name || 'N/A'}</span>
                <div className="actions-cell">
                  {checkPermission('projects.update') && (
                    <button
                      className="btn-action edit"
                      onClick={(e) => handleOpenEdit(project, e)}
                    >
                      Edit
                    </button>
                  )}
                  {checkPermission('projects.archive') && project.status !== 'archived' && (
                    <button
                      className="btn-action archive"
                      onClick={(e) => handleOpenArchive(project, e)}
                    >
                      Archive
                    </button>
                  )}
                  {checkPermission('projects.delete') && (
                    <button
                      className="btn-action delete"
                      onClick={(e) => handleOpenDelete(project, e)}
                    >
                      Delete
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {!loading && meta.total > 0 && (
        <div className="pagination-bar">
          <span className="pagination-info">
            Showing {(meta.current_page - 1) * meta.per_page + 1} to{' '}
            {Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total} projects
          </span>

          <div className="pagination-controls">
            <button
              className="btn-pagination"
              disabled={meta.current_page <= 1}
              onClick={() => updateFilter('page', meta.current_page - 1)}
            >
              ← Previous
            </button>

            <span className="page-current">
              Page {meta.current_page} of {meta.last_page || 1}
            </span>

            <button
              className="btn-pagination"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => updateFilter('page', meta.current_page + 1)}
            >
              Next →
            </button>
          </div>
        </div>
      )}

      <ProjectFormModal
        isOpen={formModalOpen}
        onClose={() => setFormModalOpen(false)}
        onSuccess={fetchProjects}
        project={selectedProject}
      />

      <ConfirmModal
        isOpen={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        onConfirm={handleConfirmDelete}
        title="Delete Project"
        message={`Are you sure you want to delete "${projectToDelete?.title}"? This action cannot be undone.`}
        confirmText="Delete Project"
        danger={true}
        loading={deleteLoading}
      />

      <ConfirmModal
        isOpen={archiveModalOpen}
        onClose={() => setArchiveModalOpen(false)}
        onConfirm={handleConfirmArchive}
        title="Archive Project"
        message={`Are you sure you want to archive "${projectToArchive?.title}"? It will be hidden from active lists.`}
        confirmText="Archive Project"
        danger={false}
        loading={archiveLoading}
      />
    </div>
  );
}