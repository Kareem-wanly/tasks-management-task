import { useReducer, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import tasksApi from '../../api/tasksApi';
import projectsApi from '../../api/projectsApi';
import { useAuth } from '../../context/AuthContext';
import { useToast } from '../../context/ToastContext';
import useDebounce from '../../hooks/useDebounce';
import TaskFormModal from '../projects/TaskFormModal';
import ConfirmModal from '../projects/ConfirmModal';
import './TasksPage.css';

const initialState = {
  tasks: [],
  projects: [],
  loading: true,
  error: null,
  actionLoading: false,
  filters: {
    search: '',
    status: '',
    priority: '',
    project_id: '',
  },
  sorting: {
    sortBy: 'created_at',
    sortDir: 'desc',
  },
  pagination: {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
  },
  modals: {
    isCreateOpen: false,
    taskToDelete: null,
  },
};

function tasksReducer(state, action) {
  switch (action.type) {
    case 'FETCH_INIT':
      return { ...state, loading: true, error: null };

    case 'FETCH_TASKS_SUCCESS':
      return {
        ...state,
        loading: false,
        tasks: action.payload.tasks,
        pagination: {
          ...state.pagination,
          current_page: action.payload.current_page,
          last_page: action.payload.last_page,
          per_page: action.payload.per_page,
          total: action.payload.total,
        },
      };

    case 'FETCH_TASKS_FAILURE':
      return { ...state, loading: false, error: action.payload };

    case 'SET_PROJECTS':
      return { ...state, projects: action.payload };

    case 'SET_FILTER':
      return {
        ...state,
        filters: {
          ...state.filters,
          [action.field]: action.value,
        },
        pagination: {
          ...state.pagination,
          current_page: 1,
        },
      };

    case 'SET_SORT':
      return {
        ...state,
        sorting: {
          sortBy: action.payload.sortBy,
          sortDir: action.payload.sortDir,
        },
      };

    case 'SET_PAGE':
      return {
        ...state,
        pagination: {
          ...state.pagination,
          current_page: action.payload,
        },
      };

    case 'OPEN_CREATE_MODAL':
      return {
        ...state,
        modals: { ...state.modals, isCreateOpen: true },
      };

    case 'CLOSE_CREATE_MODAL':
      return {
        ...state,
        modals: { ...state.modals, isCreateOpen: false },
      };

    case 'OPEN_DELETE_MODAL':
      return {
        ...state,
        modals: { ...state.modals, taskToDelete: action.payload },
      };

    case 'CLOSE_DELETE_MODAL':
      return {
        ...state,
        modals: { ...state.modals, taskToDelete: null },
      };

    case 'SET_ACTION_LOADING':
      return { ...state, actionLoading: action.payload };

    default:
      return state;
  }
}

export default function TasksPage() {
  const { user } = useAuth() || {};
  const { showToast } = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const checkPermission = (permission) => {
    if (!user) return false;
    if (user.role === 'admin' || user.is_admin || user.role?.name === 'admin') return true;
    if (Array.isArray(user.permissions)) {
      return user.permissions.includes(permission) || user.permissions.some((p) => (p.name || p) === permission);
    }
    return true;
  };

  const [state, dispatch] = useReducer(tasksReducer, {
    ...initialState,
    filters: {
      search: searchParams.get('search') || '',
      status: searchParams.get('status') || '',
      priority: searchParams.get('priority') || '',
      project_id: searchParams.get('project_id') || '',
    },
    sorting: {
      sortBy: searchParams.get('sort')?.split('-')[0] || 'created_at',
      sortDir: searchParams.get('sort')?.split('-')[1] || 'desc',
    },
    pagination: {
      ...initialState.pagination,
      current_page: parseInt(searchParams.get('page') || '1', 10),
    },
  });

  const { tasks, projects, loading, error, actionLoading, filters, sorting, pagination, modals } = state;

  const debouncedSearch = useDebounce(filters.search, 400);

  useEffect(() => {
    let isMounted = true;
    const fetchProjectsList = async () => {
      try {
        const res = await projectsApi.getAll({ per_page: 100 });
        if (isMounted) {
          const list = res.data?.data || res.data || [];
          dispatch({ type: 'SET_PROJECTS', payload: Array.isArray(list) ? list : [] });
        }
      } catch (err) {
        console.error('Failed to fetch projects for filter:', err);
      }
    };
    fetchProjectsList();
    return () => { isMounted = false; };
  }, []);

  useEffect(() => {
    const params = {};
    if (debouncedSearch.trim()) params.search = debouncedSearch.trim();
    if (filters.status) params.status = filters.status;
    if (filters.priority) params.priority = filters.priority;
    if (filters.project_id) params.project_id = filters.project_id;
    if (sorting.sortBy !== 'created_at' || sorting.sortDir !== 'desc') {
      params.sort = `${sorting.sortBy}-${sorting.sortDir}`;
    }
    if (pagination.current_page > 1) {
      params.page = pagination.current_page.toString();
    }

    setSearchParams(params, { replace: true });
  }, [debouncedSearch, filters.status, filters.priority, filters.project_id, sorting, pagination.current_page, setSearchParams]);

  const fetchTasks = useCallback(async () => {
    dispatch({ type: 'FETCH_INIT' });
    try {
      const params = {
        page: pagination.current_page,
        per_page: pagination.per_page,
        sort: sorting.sortBy,
        direction: sorting.sortDir,
      };

      if (user?.id && user.role !== 'admin' && !user.is_admin) {
        params.assigned_to = user.id;
      }
      if (debouncedSearch.trim()) params.search = debouncedSearch.trim();
      if (filters.status) params.status = filters.status;
      if (filters.priority) params.priority = filters.priority;
      if (filters.project_id) params.project_id = filters.project_id;

      const res = await tasksApi.getAll(params);
      const data = res.data?.data || res.data || [];
      const meta = res.data?.meta || res.data;

      dispatch({
        type: 'FETCH_TASKS_SUCCESS',
        payload: {
          tasks: Array.isArray(data) ? data : [],
          current_page: meta?.current_page || pagination.current_page,
          last_page: meta?.last_page || 1,
          per_page: meta?.per_page || 10,
          total: meta?.total || 0,
        },
      });
    } catch (err) {
      console.error('Error fetching tasks:', err);
      dispatch({
        type: 'FETCH_TASKS_FAILURE',
        payload: err.data?.message || 'Failed to load tasks.',
      });
    }
  }, [pagination.current_page, pagination.per_page, sorting.sortBy, sorting.sortDir, debouncedSearch, filters.status, filters.priority, filters.project_id, user]);

  useEffect(() => {
    fetchTasks();
  }, [fetchTasks]);

  const handleDeleteTask = async () => {
    if (!modals.taskToDelete) return;
    dispatch({ type: 'SET_ACTION_LOADING', payload: true });
    try {
      await tasksApi.delete(modals.taskToDelete.id);
      showToast(`Task "${modals.taskToDelete.title}" was deleted successfully.`, 'success');
      dispatch({ type: 'CLOSE_DELETE_MODAL' });
      fetchTasks();
    } catch (err) {
      console.error('Failed to delete task:', err);
      showToast(err.data?.message || 'Failed to delete task.', 'error');
    } finally {
      dispatch({ type: 'SET_ACTION_LOADING', payload: false });
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
          <button className="btn-primary" onClick={() => dispatch({ type: 'OPEN_CREATE_MODAL' })}>
            + Create Task
          </button>
        )}
      </div>

      <div className="filters-card">
        <div className="filters-grid">
          <div className="filter-item search-box">
            <input
              type="text"
              id="task-search-input"
              name="search"
              placeholder="Search tasks by title..."
              value={filters.search}
              onChange={(e) => dispatch({ type: 'SET_FILTER', field: 'search', value: e.target.value })}
              className="form-control"
            />
          </div>

          <div className="filter-item">
            <select
              id="task-project-filter"
              name="project_id"
              value={filters.project_id}
              onChange={(e) => dispatch({ type: 'SET_FILTER', field: 'project_id', value: e.target.value })}
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

          <div className="filter-item">
            <select
              id="task-status-filter"
              name="status"
              value={filters.status}
              onChange={(e) => dispatch({ type: 'SET_FILTER', field: 'status', value: e.target.value })}
              className="form-control"
            >
              <option value="">All Statuses</option>
              <option value="todo">To Do</option>
              <option value="in_progress">In Progress</option>
              <option value="review">Review</option>
              <option value="completed">Completed</option>
            </select>
          </div>

          <div className="filter-item">
            <select
              id="task-priority-filter"
              name="priority"
              value={filters.priority}
              onChange={(e) => dispatch({ type: 'SET_FILTER', field: 'priority', value: e.target.value })}
              className="form-control"
            >
              <option value="">All Priorities</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>

          <div className="filter-item">
            <select
              id="task-sort-filter"
              name="sort"
              value={`${sorting.sortBy}-${sorting.sortDir}`}
              onChange={(e) => {
                const [sort, dir] = e.target.value.split('-');
                dispatch({ type: 'SET_SORT', payload: { sortBy: sort, sortDir: dir } });
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
                            onClick={() => dispatch({ type: 'OPEN_DELETE_MODAL', payload: task })}
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

        {!loading && tasks.length > 0 && (
          <div className="pagination-bar">
            <span className="pagination-info">
              Showing page {pagination.current_page} of {pagination.last_page} ({pagination.total} total tasks)
            </span>
            <div className="pagination-controls">
              <button
                className="btn-page"
                disabled={pagination.current_page <= 1}
                onClick={() => dispatch({ type: 'SET_PAGE', payload: pagination.current_page - 1 })}
              >
                Previous
              </button>
              <button
                className="btn-page"
                disabled={pagination.current_page >= pagination.last_page}
                onClick={() => dispatch({ type: 'SET_PAGE', payload: pagination.current_page + 1 })}
              >
                Next
              </button>
            </div>
          </div>
        )}
      </div>

      {modals.isCreateOpen && (
        <TaskFormModal
          isOpen={modals.isCreateOpen}
          onClose={() => dispatch({ type: 'CLOSE_CREATE_MODAL' })}
          onSuccess={() => {
            dispatch({ type: 'CLOSE_CREATE_MODAL' });
            fetchTasks();
          }}
        />
      )}

      {modals.taskToDelete && (
        <ConfirmModal
          isOpen={Boolean(modals.taskToDelete)}
          title="Delete Task"
          message={`Are you sure you want to delete task "${modals.taskToDelete.title}"?`}
          confirmText="Delete"
          isDanger={true}
          loading={actionLoading}
          onConfirm={handleDeleteTask}
          onClose={() => dispatch({ type: 'CLOSE_DELETE_MODAL' })}
        />
      )}
    </div>
  );
}