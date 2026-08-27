import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import projectsApi from '../../api/projectsApi';
import tasksApi from '../../api/tasksApi';
import './DashboardPage.css';

export default function DashboardPage() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [projects, setProjects] = useState([]);
  const [tasks, setTasks] = useState([]);

  useEffect(() => {
    let isMounted = true;

    async function fetchDashboardData() {
      try {
        setLoading(true);
        setError(null);

        const [projectsRes, tasksRes] = await Promise.all([
          projectsApi.getAll({ per_page: 150 }),
          tasksApi.getAll({ per_page: 150 }),
        ]);

        const extractData = (res) => {
          if (!res) return [];
          if (Array.isArray(res.data?.data)) return res.data.data;
          if (Array.isArray(res.data)) return res.data;
          if (Array.isArray(res.projects)) return res.projects;
          if (Array.isArray(res.tasks)) return res.tasks;
          if (Array.isArray(res)) return res;
          return [];
        };

        if (isMounted) {
          setProjects(extractData(projectsRes));
          setTasks(extractData(tasksRes));
        }
      } catch (err) {
        console.error('Failed to load dashboard data:', err);
        if (isMounted) {
          setError(err.data?.message || 'Failed to load dashboard metrics. Please try again.');
        }
      } finally {
        if (isMounted) setLoading(false);
      }
    }

    fetchDashboardData();
    return () => { isMounted = false; };
  }, []);

  const totalProjects = projects.length;
  const activeProjects = projects.filter((p) => p.status === 'active').length;

  const myTasks = tasks.filter((t) => {
    const assigneeId = t.assigned_to?.id || t.assigned_user?.id || t.assignee?.id || t.assigned_to;
    return Number(assigneeId) === Number(user?.id);
  });

  const inProgressTasks = tasks.filter((t) => t.status === 'in_progress').length;
  const reviewTasks = tasks.filter((t) => t.status === 'review').length;
  const completedTasks = tasks.filter((t) => t.status === 'completed').length;
  const todoTasks = tasks.filter((t) => t.status === 'todo' || t.status === 'pending').length;

  const startOfToday = new Date().setHours(0, 0, 0, 0);
  const overdueTasks = tasks.filter((t) => {
    if (t.status === 'completed') return false;
    if (t.is_overdue) return true;
    if (!t.due_date) return false;
    return new Date(t.due_date).getTime() < startOfToday;
  }).length;

  const priorityCounts = {
    urgent: tasks.filter((t) => t.priority === 'urgent').length,
    high: tasks.filter((t) => t.priority === 'high').length,
    medium: tasks.filter((t) => t.priority === 'medium').length,
    low: tasks.filter((t) => t.priority === 'low').length,
  };

  const totalStatusTasks = tasks.length || 1;
  const radius = 38;
  const circumference = 2 * Math.PI * radius; 

  const todoStroke = (todoTasks / totalStatusTasks) * circumference;
  const inProgressStroke = (inProgressTasks / totalStatusTasks) * circumference;
  const reviewStroke = (reviewTasks / totalStatusTasks) * circumference;
  const completedStroke = (completedTasks / totalStatusTasks) * circumference;

  const inProgressOffset = -todoStroke;
  const reviewOffset = -(todoStroke + inProgressStroke);
  const completedOffset = -(todoStroke + inProgressStroke + reviewStroke);

  if (loading) {
    return (
      <div className="dashboard-loading">
        <div className="spinner" />
        <span>Loading analytics & overview...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="dashboard-container">
        <div className="alert alert-danger">{error}</div>
      </div>
    );
  }

  return (
    <div className="dashboard-container">
      <div className="dashboard-header">
        <div>
          <h1>Dashboard Overview</h1>
          <p>Welcome back, <strong>{user?.name}</strong>. Here is your team's real-time progress.</p>
        </div>
        <div className="header-actions">
          <Link to="/tasks" className="btn-header-link">View All Tasks &rarr;</Link>
        </div>
      </div>

      <section className="metrics-grid">
        <div className="metric-card">
          <span className="metric-title">Accessible Projects</span>
          <span className="metric-value">{totalProjects}</span>
          <span className="metric-badge primary">Total Access</span>
        </div>

        <div className="metric-card">
          <span className="metric-title">Active Projects</span>
          <span className="metric-value">{activeProjects}</span>
          <span className="metric-badge info">In Progress</span>
        </div>

        <div className="metric-card">
          <span className="metric-title">Assigned to Me</span>
          <span className="metric-value">{myTasks.length}</span>
          <span className="metric-badge neutral">My Queue</span>
        </div>

        <div className="metric-card">
          <span className="metric-title">Tasks In Progress</span>
          <span className="metric-value">{inProgressTasks}</span>
          <span className="metric-badge warning">Underway</span>
        </div>

        <div className="metric-card">
          <span className="metric-title">Completed Tasks</span>
          <span className="metric-value">{completedTasks}</span>
          <span className="metric-badge success">Finished</span>
        </div>

        <div className="metric-card">
          <span className="metric-title">Overdue Tasks</span>
          <span className="metric-value danger-text">{overdueTasks}</span>
          <span className="metric-badge danger">Requires Action</span>
        </div>
      </section>

      <section className="charts-grid">
        <div className="chart-card">
          <div className="chart-card-header">
            <h3>Tasks by Status</h3>
            <span className="chart-total-count">{tasks.length} Total</span>
          </div>

          {tasks.length === 0 ? (
            <div className="chart-empty-state">No tasks available to graph.</div>
          ) : (
            <>
              <div className="donut-chart-wrapper">
                <svg viewBox="0 0 100 100" className="donut-svg">
                  <circle cx="50" cy="50" r={radius} className="donut-bg" />

                  <g transform="rotate(-90 50 50)">
                    <circle
                      cx="50" cy="50" r={radius}
                      className="donut-segment segment-todo"
                      strokeDasharray={`${todoStroke} ${circumference}`}
                      strokeDashoffset="0"
                    />
                    <circle
                      cx="50" cy="50" r={radius}
                      className="donut-segment segment-progress"
                      strokeDasharray={`${inProgressStroke} ${circumference}`}
                      strokeDashoffset={inProgressOffset}
                    />
                    <circle
                      cx="50" cy="50" r={radius}
                      className="donut-segment segment-review"
                      strokeDasharray={`${reviewStroke} ${circumference}`}
                      strokeDashoffset={reviewOffset}
                    />
                    <circle
                      cx="50" cy="50" r={radius}
                      className="donut-segment segment-completed"
                      strokeDasharray={`${completedStroke} ${circumference}`}
                      strokeDashoffset={completedOffset}
                    />
                  </g>
                </svg>

                <div className="donut-center-label">
                  <span className="donut-center-val">{completedTasks}</span>
                  <span className="donut-center-text">Completed</span>
                </div>
              </div>

              <div className="chart-legend">
                <div className="legend-item">
                  <span className="legend-dot todo" />
                  <span>To Do ({todoTasks})</span>
                </div>
                <div className="legend-item">
                  <span className="legend-dot progress" />
                  <span>In Progress ({inProgressTasks})</span>
                </div>
                <div className="legend-item">
                  <span className="legend-dot review" />
                  <span>Review ({reviewTasks})</span>
                </div>
                <div className="legend-item">
                  <span className="legend-dot completed" />
                  <span>Completed ({completedTasks})</span>
                </div>
              </div>
            </>
          )}
        </div>

        <div className="chart-card">
          <div className="chart-card-header">
            <h3>Tasks by Priority</h3>
            <span className="chart-total-count">{tasks.length} Total</span>
          </div>

          {tasks.length === 0 ? (
            <div className="chart-empty-state">No tasks available to graph.</div>
          ) : (
            <div className="priority-bars-list">
              {priorityCounts.urgent > 0 && (
                <div className="priority-bar-item">
                  <div className="bar-info">
                    <span className="priority-label urgent">Urgent Priority</span>
                    <span className="bar-count">{priorityCounts.urgent}</span>
                  </div>
                  <div className="bar-track">
                    <div
                      className="bar-fill fill-urgent"
                      style={{ width: `${(priorityCounts.urgent / tasks.length) * 100}%` }}
                    />
                  </div>
                </div>
              )}

              <div className="priority-bar-item">
                <div className="bar-info">
                  <span className="priority-label high">High Priority</span>
                  <span className="bar-count">{priorityCounts.high}</span>
                </div>
                <div className="bar-track">
                  <div
                    className="bar-fill fill-high"
                    style={{ width: `${tasks.length ? (priorityCounts.high / tasks.length) * 100 : 0}%` }}
                  />
                </div>
              </div>

              <div className="priority-bar-item">
                <div className="bar-info">
                  <span className="priority-label medium">Medium Priority</span>
                  <span className="bar-count">{priorityCounts.medium}</span>
                </div>
                <div className="bar-track">
                  <div
                    className="bar-fill fill-medium"
                    style={{ width: `${tasks.length ? (priorityCounts.medium / tasks.length) * 100 : 0}%` }}
                  />
                </div>
              </div>

              <div className="priority-bar-item">
                <div className="bar-info">
                  <span className="priority-label low">Low Priority</span>
                  <span className="bar-count">{priorityCounts.low}</span>
                </div>
                <div className="bar-track">
                  <div
                    className="bar-fill fill-low"
                    style={{ width: `${tasks.length ? (priorityCounts.low / tasks.length) * 100 : 0}%` }}
                  />
                </div>
              </div>
            </div>
          )}
        </div>

        <div className="chart-card full-width">
          <div className="chart-card-header">
            <h3>Project Completion Progress</h3>
            <Link to="/projects" className="link-subtle">View All &rarr;</Link>
          </div>

          <div className="project-progress-list">
            {projects.length === 0 ? (
              <p className="empty-text">No active projects available.</p>
            ) : (
              projects.slice(0, 6).map((project) => {
                const projectTasks = tasks.filter((t) => {
                  const pId = t.project_id || t.project?.id;
                  return Number(pId) === Number(project.id);
                });
                const projCompleted = projectTasks.filter((t) => t.status === 'completed').length;
                const progressPercent = projectTasks.length
                  ? Math.round((projCompleted / projectTasks.length) * 100)
                  : project.status === 'completed'
                  ? 100
                  : 0;

                const projectName = project.title || project.name || 'Untitled Project';

                return (
                  <div key={project.id} className="project-progress-row">
                    <div className="project-row-details">
                      <Link to={`/projects/${project.id}`} className="project-row-title">
                        {projectName}
                      </Link>
                      <span className="project-row-stats">
                        {projCompleted} of {projectTasks.length} tasks done ({progressPercent}%)
                      </span>
                    </div>
                    <div className="bar-track">
                      <div
                        className="bar-fill fill-project"
                        style={{ width: `${progressPercent}%` }}
                      />
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>
      </section>
    </div>
  );
}