import { useState, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import tasksApi from '../../api/tasksApi';
import projectsApi from '../../api/projectsApi';
import { useAuth } from '../../context/AuthContext';
import TaskFormModal from '../projects/TaskFormModal';
import ConfirmModal from '../projects/ConfirmModal';
import './TasksPage.css';

export default function TasksPage() {
  const navigate = useNavigate();
  const { user } = useAuth() || {};

  const checkPermission = (permission) => {
    if (!user) return false;
    if (user.role === 'admin' || user.is_admin || user.role?.name === 'admin') return true;
    if (Array.isArray(user.permissions)) {
      return user.permissions.includes(permission) || user.permissions.some((p) => (p.name || p) === permission);
    }
    return true;
  };

  const [tasks, setTasks] = useState([]);
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [projectFilter, setProjectFilter] = useState('');
  const [sortBy, setSortBy] = useState('created_at');
  const [sortDir, setSortDir] = useState('desc');
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  });

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [taskToDelete, setTaskToDelete] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);

  useEffect(() => {
    let isMounted = true;
    const fetchProjectsList = async () => {
      try {
        const res = await projectsApi.getAll({ per_page: 100 });
        if (isMounted) {
          const list = res.data?.data || res.data || [];
          setProjects(Array.isArray(list) ? list : []);
        }
      } catch (err) {
        console.error('Failed to fetch projects for filter:', err);
      }
    };
    fetchProjectsList();
    return () => { isMounted = false; };
  }, []);

  const fetchTasks = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        page: pagination.current_page,
        per_page: pagination.per_page,
        sort: sortBy,
        direction: sortDir,
      };

      if (user?.id && user.role !== 'admin' && !user.is_admin) {
        params.assigned_to = user.id;
      }
      if (search.trim()) params.search = search.trim();
      if (statusFilter) params.status = statusFilter;
      if (priorityFilter) params.priority = priorityFilter;
      if (projectFilter) params.project_id = projectFilter;

      const res = await tasksApi.getAll(params);
      const data = res.data?.data || res.data || [];
      const meta = res.data?.meta || res.data;

      setTasks(Array.isArray(data) ? data : []);
      if (meta && meta.current_page) {
        setPagination((prev) => ({
          ...prev,
          current_page: meta.current_page,
          last_page: meta.last_page || 1,
          per_page: meta.per_page || 10,
          total: meta.total || 0,
        }));
      }
    } catch (err) {
      console.error('Error fetching tasks:', err);
      setError(err.data?.message || 'Failed to load tasks.');
    } finally {
      setLoading(false);
    }
  }, [pagination.current_page, pagination.per_page, sortBy, sortDir, search, statusFilter, priorityFilter, projectFilter, user]);

  useEffect(() => {
    fetchTasks();
  }, [fetchTasks]);

  const handleSearchChange = (e) => {
    setSearch(e.target.value);
    setPagination((prev) => ({ ...prev, current_page: 1 }));
  };

  const handleDeleteTask = async () => {
    if (!taskToDelete) return;
    setActionLoading(true);
    try {
      await tasksApi.delete(taskToDelete.id);
      setTaskToDelete(null);
      fetchTasks();
    } catch (err) {
      console.error('Failed to delete task:', err);
      alert(err.data?.message || 'Failed to delete task.');
    } finally {
      setActionLoading(false);
    }
  };

  const getStatusBadge = (status) => {
    const map = {
      todo: { label: 'To Do', class: 'badge-secondary' },
      in_progress: { label: 'In Progress', class: 'badge-warning' },
      completed: { label: 'Completed', class: 'badge-success' },
      review: { label: 'Review', class: 'badge-info' },
    };
    const s = map[status] || { label: status || 'To Do', class: 'badge-secondary' };
    return <span className={`badge ${s.class}`}>{s.label}</span>;
  };

  const getPriorityBadge = (priority) => {
    const map = {
      low: { label: 'Low', class: 'badge-muted' },
      medium: { label: 'Medium', class: 'badge-info' },
      high: { label: 'High', class: 'badge-warning' },
      urgent: { label: 'Urgent', class: 'badge-danger' },
    };
    const p = map[priority] || { label: priority || 'Medium', class: 'badge-muted' };
    return <span className={`badge ${p.class}`}>{p.label}</span>;
  };

  return (
    <div className="tasks-page-container">
      <div className="page-header">
        <div>
          <h1>My Tasks</h1>
          <p className="subtitle">Manage, filter, and track tasks assigned to you</p>
        </div>
        {checkPermission('tasks.create') && (
          <button className="btn-primary" onClick={() => setIsCreateOpen(true)}>
            + Create Task
          </button>
        )}
      </div>

      {/* Filters Card */}
      <div className="filters-card">
        <div className="filters-grid">
          {/* Search */}
          <div className="filter-item search-box">
            <input
              type="text"
              id="task-search-input"
              name="search"
              placeholder="Search tasks by title..."
              value={search}
              onChange={handleSearchChange}
              className="form-control"
            />
          </div>

          {/* Project Filter */}
          <div className="filter-item">
            <select
              id="task-project-filter"
              name="project_id"
              value={projectFilter}
              onChange={(e) => {
                setProjectFilter(e.target.value);
                setPagination((prev) => ({ ...prev, current_page: 1 }));
              }}
              className="form-control"
            >
              <option value="">All Projects</option>
              {projects.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.title || p.name}
                </option>
              ))}
            </select>
          </div>

          {/* Status Filter */}
          <div className="filter-item">
            <select
              id="task-status-filter"
              name="status"
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPagination((prev) => ({ ...prev, current_page: 1 }));
              }}
              className="form-control"
            >
              <option value="">All Statuses</option>
              <option value="todo">To Do</option>
              <option value="in_progress">In Progress</option>
              <option value="review">Review</option>
              <option value="completed">Completed</option>
            </select>
          </div>

          {/* Priority Filter */}
          <div className="filter-item">
            <select
              id="task-priority-filter"
              name="priority"
              value={priorityFilter}
              onChange={(e) => {
                setPriorityFilter(e.target.value);
                setPagination((prev) => ({ ...prev, current_page: 1 }));
              }}
              className="form-control"
            >
              <option value="">All Priorities</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>

          {/* Sort By */}
          <div className="filter-item">
            <select
              id="task-sort-filter"
              name="sort"
              value={`${sortBy}-${sortDir}`}
              onChange={(e) => {
                const [sort, dir] = e.target.value.split('-');
                setSortBy(sort);
                setSortDir(dir);
              }}
              className="form-control"
            >
              <option value="created_at-desc">Newest First</option>
              <option value="created_at-asc">Oldest First</option>
              <option value="due_date-asc">Due Date (Earliest)</option>
              <option value="due_date-desc">Due Date (Latest)</option>
            </select>
          </div>
        </div>
      </div>

      {error && <div className="alert alert-danger mb-3">{error}</div>}

      {/* Tasks Table */}
      <div className="tasks-table-card">
        {loading ? (
          <div className="loading-state">
            <p>Loading tasks...</p>
          </div>
        ) : tasks.length === 0 ? (
          <div className="empty-state">
            <div className="empty-icon">📋</div>
            <h3>No tasks found</h3>
            <p>You have no tasks assigned to you matching the selected filters.</p>
          </div>
        ) : (
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Task</th>
                  <th>Project</th>
                  <th>Status</th>
                  <th>Priority</th>
                  <th>Assignee</th>
                  <th>Due Date</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {tasks.map((task) => {
                  const isOverdue =
                    task.due_date &&
                    task.status !== 'completed' &&
                    new Date(task.due_date) < new Date().setHours(0, 0, 0, 0);

                  return (
                    <tr key={task.id}>
                      <td>
                        <Link to={`/tasks/${task.id}`} className="task-title-link">
                          {task.title}
                        </Link>
                        {task.description && (
                          <p className="task-desc-snippet">
                            {task.description.length > 60
                              ? `${task.description.substring(0, 60)}...`
                              : task.description}
                          </p>
                        )}
                      </td>
                      <td>
                        {task.project ? (
                          <Link to={`/projects/${task.project.id}`} className="project-link-badge">
                            {task.project.title || task.project.name}
                          </Link>
                        ) : (
                          <span className="text-muted">No Project</span>
                        )}
                      </td>
                      <td>{getStatusBadge(task.status)}</td>
                      <td>{getPriorityBadge(task.priority)}</td>
                      <td>
                        {task.assigned_user || task.assignee ? (
                          <div className="assignee-cell">
                            <span className="user-avatar-sm">
                              {(task.assigned_user?.name || task.assignee?.name || 'U')[0].toUpperCase()}
                            </span>
                            <span>{task.assigned_user?.name || task.assignee?.name}</span>
                          </div>
                        ) : (
                          <span className="text-muted">Unassigned</span>
                        )}
                      </td>
                      <td>
                        {task.due_date ? (
                          <span style={{ color: isOverdue ? '#dc2626' : 'inherit', fontWeight: isOverdue ? '600' : 'normal' }}>
                            {task.due_date.split('T')[0]} {isOverdue && '⚠️'}
                          </span>
                        ) : (
                          <span className="text-muted">No date</span>
                        )}
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        {checkPermission('tasks.delete') && (
                          <button
                            type="button"
                            className="btn-delete"
                            onClick={() => setTaskToDelete(task)}
                          >
                            Delete
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination Footer */}
        {!loading && tasks.length > 0 && (
          <div className="pagination-bar">
            <span className="pagination-info">
              Showing page {pagination.current_page} of {pagination.last_page} ({pagination.total} total tasks)
            </span>
            <div className="pagination-controls">
              <button
                className="btn-page"
                disabled={pagination.current_page <= 1}
                onClick={() =>
                  setPagination((prev) => ({ ...prev, current_page: prev.current_page - 1 }))
                }
              >
                Previous
              </button>
              <button
                className="btn-page"
                disabled={pagination.current_page >= pagination.last_page}
                onClick={() =>
                  setPagination((prev) => ({ ...prev, current_page: prev.current_page + 1 }))
                }
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Modals */}
      {isCreateOpen && (
        <TaskFormModal
          isOpen={isCreateOpen}
          onClose={() => setIsCreateOpen(false)}
          onSuccess={() => {
            setIsCreateOpen(false);
            fetchTasks();
          }}
        />
      )}

      {taskToDelete && (
        <ConfirmModal
          isOpen={Boolean(taskToDelete)}
          title="Delete Task"
          message={`Are you sure you want to delete task "${taskToDelete.title}"?`}
          confirmText="Delete"
          isDanger={true}
          loading={actionLoading}
          onConfirm={handleDeleteTask}
          onClose={() => setTaskToDelete(null)}
        />
      )}
    </div>
  );
}