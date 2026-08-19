import { useState, useEffect } from 'react';
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
    async function fetchDashboardData() {
      try {
        setLoading(true);
        setError(null);

        const [projectsRes, tasksRes] = await Promise.all([
          projectsApi.getAll({ per_page: 100 }),
          tasksApi.getAll({ per_page: 100 }),
        ]);

        const projectsData = projectsRes?.data || projectsRes || [];
        const tasksData = tasksRes?.data || tasksRes || [];

        setProjects(Array.isArray(projectsData) ? projectsData : []);
        setTasks(Array.isArray(tasksData) ? tasksData : []);
      } catch (err) {
        console.error('Failed to load dashboard data:', err);
        setError('Failed to load dashboard metrics. Please try again.');
      } finally {
        setLoading(false);
      }
    }

    fetchDashboardData();
  }, []);

  const totalProjects = projects.length;
  const activeProjects = projects.filter((p) => p.status === 'active').length;
  const myTasks = tasks.filter((t) => t.assigned_to === user?.id);
  const inProgressTasks = tasks.filter((t) => t.status === 'in_progress').length;
  const completedTasks = tasks.filter((t) => t.status === 'completed').length;
  const overdueTasks = tasks.filter(
    (t) => t.is_overdue || (t.due_date && t.status !== 'completed' && new Date(t.due_date) < new Date())
  ).length;

  const priorityCounts = {
    high: tasks.filter((t) => t.priority === 'high').length,
    medium: tasks.filter((t) => t.priority === 'medium').length,
    low: tasks.filter((t) => t.priority === 'low').length,
  };

  const statusCounts = {
    todo: tasks.filter((t) => t.status === 'todo' || t.status === 'pending').length,
    in_progress: inProgressTasks,
    completed: completedTasks,
  };
  const totalStatusTasks = tasks.length || 1;

  const radius = 40;
  const circumference = 2 * Math.PI * radius;
  const todoStroke = (statusCounts.todo / totalStatusTasks) * circumference;
  const inProgressStroke = (statusCounts.in_progress / totalStatusTasks) * circumference;
  const completedStroke = (statusCounts.completed / totalStatusTasks) * circumference;

  if (loading) {
    return (
      <div className="dashboard-loading">
        <div className="spinner" />
        <span>Loading analytics...</span>
      </div>
    );
  }

  if (error) {
    return <div className="dashboard-error">{error}</div>;
  }

  return (
    <div className="dashboard-container">
      <div className="dashboard-header">
        <h1>Dashboard Overview</h1>
        <p>Welcome back, <strong>{user?.name}</strong>. Here is your system performance summary.</p>
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
          <span className="metric-badge neutral">Personal Queue</span>
        </div>

        <div className="metric-card">
          <span className="metric-title">Tasks In Progress</span>
          <span className="metric-value">{inProgressTasks}</span>
          <span className="metric-badge warning">Underway</span>
        </div>

        <div className="metric-card">
          <span className="metric-title">Completed Tasks</span>
          <span className="metric-value">{completedTasks}</span>
          <span className="metric-badge success">Done</span>
        </div>

        <div className="metric-card">
          <span className="metric-title">Overdue Tasks</span>
          <span className="metric-value danger-text">{overdueTasks}</span>
          <span className="metric-badge danger">Action Needed</span>
        </div>
      </section>

      <section className="charts-grid">
        <div className="chart-card">
          <div className="chart-card-header">
            <h3>Tasks by Status</h3>
            <span className="chart-total-count">{tasks.length} Total</span>
          </div>

          <div className="donut-chart-wrapper">
            <svg viewBox="0 0 100 100" className="donut-svg">
              <circle
                cx="50"
                cy="50"
                r={radius}
                className="donut-segment segment-todo"
                strokeDasharray={`${todoStroke} ${circumference}`}
                strokeDashoffset="0"
              />
              <circle
                cx="50"
                cy="50"
                r={radius}
                className="donut-segment segment-progress"
                strokeDasharray={`${inProgressStroke} ${circumference}`}
                strokeDashoffset={-todoStroke}
              />
              <circle
                cx="50"
                cy="50"
                r={radius}
                className="donut-segment segment-completed"
                strokeDasharray={`${completedStroke} ${circumference}`}
                strokeDashoffset={-(todoStroke + inProgressStroke)}
              />
            </svg>

            <div className="donut-center-label">
              <span className="donut-center-val">{completedTasks}</span>
              <span className="donut-center-text">Completed</span>
            </div>
          </div>

          <div className="chart-legend">
            <div className="legend-item">
              <span className="legend-dot todo" />
              <span>To Do ({statusCounts.todo})</span>
            </div>
            <div className="legend-item">
              <span className="legend-dot progress" />
              <span>In Progress ({statusCounts.in_progress})</span>
            </div>
            <div className="legend-item">
              <span className="legend-dot completed" />
              <span>Completed ({statusCounts.completed})</span>
            </div>
          </div>
        </div>

        {/* Tasks by Priority Chart (Plain CSS) */}
        <div className="chart-card">
          <div className="chart-card-header">
            <h3>Tasks by Priority</h3>
            <span className="chart-total-count">{tasks.length} Total</span>
          </div>

          <div className="priority-bars-list">
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
        </div>

        <div className="chart-card full-width">
          <div className="chart-card-header">
            <h3>Project Completion Progress</h3>
          </div>

          <div className="project-progress-list">
            {projects.length === 0 ? (
              <p className="empty-text">No active projects available.</p>
            ) : (
              projects.slice(0, 5).map((project) => {
                const projectTasks = tasks.filter((t) => t.project_id === project.id);
                const projCompleted = projectTasks.filter((t) => t.status === 'completed').length;
                const progressPercent = projectTasks.length
                  ? Math.round((projCompleted / projectTasks.length) * 100)
                  : project.status === 'completed'
                  ? 100
                  : 0;

                return (
                  <div key={project.id} className="project-progress-row">
                    <div className="project-row-details">
                      <span className="project-row-title">{project.title}</span>
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