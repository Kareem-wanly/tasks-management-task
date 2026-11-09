import { Link } from 'react-router-dom';
import { FolderKanbanIcon, KanbanIcon } from 'lucide-react';
import PageHeader from '@/components/layout/PageHeader';
import { Button } from '@/components/ui/button';
import ErrorState from '@/components/ui/ErrorState';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/lib/auth/AuthContext';
import { errorMessage } from '@/lib/apiClient';
import { daysFromNow } from '@/utils/dates';
import { formatLongDate } from '@/utils/format';
import DashboardKpis, { type Kpi } from './components/DashboardKpis';
import DashboardSideColumn from './components/DashboardSideColumn';
import MyWorkPanel from './components/MyWorkPanel';
import ProjectProgressPanel from './components/ProjectProgressPanel';
import RecentActivityPanel from './components/RecentActivityPanel';
import useDashboardData from './hooks/useDashboardData';
import { dueWithin, greeting, openTasksByDue, projectsByUrgency } from './utils';

export default function DashboardPage() {
  const { user, can } = useAuth();
  const data = useDashboardData(user?.id);

  const myOpen = openTasksByDue(data.myTasks);
  const myDueSoon = dueWithin(myOpen, 7);
  const activeProjects = data.projects.filter((p) => p.status === 'active' || p.status === 'in_progress').length;
  const completion = data.tasksTotal > 0 ? Math.round((data.completedTotal / data.tasksTotal) * 100) : 0;
  const mine = user ? `/tasks?assigned_to=${user.id}` : '/tasks';

  const kpis: Kpi[] = [
    { key: 'mine', label: 'My open tasks', value: myOpen.length, hint: 'Assigned to you', to: mine },
    {
      key: 'soon',
      label: 'Due in 7 days',
      value: myDueSoon,
      hint: 'Yours, not yet overdue',
      to: `${mine}&due_date_from=${daysFromNow(0)}&due_date_to=${daysFromNow(7)}`,
    },
    {
      key: 'overdue',
      label: 'Overdue',
      value: data.overdueTotal,
      hint: data.overdueTotal > 0 ? 'Across all projects' : 'Everything on schedule',
      to: '/tasks?overdue=true',
      tone: data.overdueTotal > 0 ? 'danger' : 'default',
    },
    {
      key: 'completion',
      label: 'Completion',
      value: `${completion}%`,
      hint: `${data.completedTotal} of ${data.tasksTotal} tasks done`,
      to: '/tasks?status=completed',
      tone: completion === 100 && data.tasksTotal > 0 ? 'success' : 'default',
    },
    { key: 'projects', label: 'Active projects', value: activeProjects, hint: `${data.projects.length} in total`, to: '/projects' },
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow={formatLongDate()}
        title={greeting(user?.name)}
        description="Your queue, deadlines and project health in one place."
        actions={
          <>
            <Button asChild variant="outline">
              <Link to="/projects">
                <FolderKanbanIcon />
                Projects
              </Link>
            </Button>
            <Button asChild variant="brand">
              <Link to="/tasks">
                <KanbanIcon />
                Open tasks
              </Link>
            </Button>
          </>
        }
      />

      {data.isError ? (
        <ErrorState
          title="Failed to load dashboard"
          message={errorMessage(data.error, 'Failed to load dashboard metrics. Please try again.')}
          onRetry={data.refetch}
        />
      ) : data.isPending ? (
        <div className="space-y-4" aria-busy="true" aria-label="Loading your overview">
          <Skeleton className="h-24 rounded-lg" />
          <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <Skeleton className="h-80 rounded-lg" />
            <Skeleton className="h-80 rounded-lg" />
          </div>
        </div>
      ) : (
        <>
          <DashboardKpis items={kpis} />
          <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div className="min-w-0 space-y-4">
              <MyWorkPanel tasks={myOpen} allHref={mine} />
              <ProjectProgressPanel projects={projectsByUrgency(data.projects)} />
              {can('activities.view') && <RecentActivityPanel />}
            </div>
            <DashboardSideColumn tasks={data.tasks} />
          </div>
        </>
      )}
    </div>
  );
}
