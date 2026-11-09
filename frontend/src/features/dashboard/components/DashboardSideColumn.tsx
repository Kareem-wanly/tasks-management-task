import BreakdownBar, { type BreakdownSegment } from '@/components/data/BreakdownBar';
import Panel from '@/components/layout/Panel';
import { TASK_PRIORITY_LABELS, TASK_STATUS_LABELS } from '@/constants/statuses';
import type { Task, TaskPriority, TaskStatus } from '@/types/api';
import { countTasks } from '../utils';
import TasksCreatedPanel from './TasksCreatedPanel';

interface DashboardSideColumnProps {
  /** The latest tasks the breakdowns and chart are computed from. */
  tasks: Task[];
}

const STATUS_COLORS: [TaskStatus, string][] = [
  ['todo', 'bg-chart-3'],
  ['in_progress', 'bg-info'],
  ['review', 'bg-brand'],
  ['completed', 'bg-success'],
];

const PRIORITY_COLORS: [TaskPriority, string][] = [
  ['urgent', 'bg-danger'],
  ['high', 'bg-chart-5'],
  ['medium', 'bg-chart-2'],
  ['low', 'bg-chart-3'],
];

/** Workload breakdowns and the creation trend, stacked in the narrow column. */
export default function DashboardSideColumn({ tasks }: DashboardSideColumnProps) {
  const { byStatus, byPriority } = countTasks(tasks);

  const statusSegments: BreakdownSegment[] = STATUS_COLORS.map(([status, colorClass]) => ({
    key: status,
    label: TASK_STATUS_LABELS[status],
    value: byStatus[status],
    colorClass,
  }));
  const prioritySegments: BreakdownSegment[] = PRIORITY_COLORS.map(([priority, colorClass]) => ({
    key: priority,
    label: TASK_PRIORITY_LABELS[priority],
    value: byPriority[priority],
    colorClass,
  }));

  return (
    <div className="space-y-4">
      <Panel title="Workload" description={`Latest ${tasks.length} tasks`}>
        <div className="space-y-5">
          <section className="space-y-2.5">
            <h3 className="text-xs font-medium text-muted-foreground">By status</h3>
            <BreakdownBar segments={statusSegments} />
          </section>
          <section className="space-y-2.5 border-t border-border pt-4">
            <h3 className="text-xs font-medium text-muted-foreground">By priority</h3>
            <BreakdownBar segments={prioritySegments} />
          </section>
        </div>
      </Panel>
      <TasksCreatedPanel tasks={tasks} />
    </div>
  );
}
