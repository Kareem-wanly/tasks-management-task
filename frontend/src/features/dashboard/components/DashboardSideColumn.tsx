import BreakdownBar, { type BreakdownSegment } from '@/components/data/BreakdownBar';
import Panel from '@/components/layout/Panel';
import type { Tone } from '@/constants/statuses';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import type { Task } from '@/types/api';
import { countTasks } from '../utils';
import TasksCreatedPanel from './TasksCreatedPanel';

interface DashboardSideColumnProps {
  /** The latest tasks the breakdowns and chart are computed from. */
  tasks: Task[];
}

const TONE_BAR: Record<Tone, string> = {
  neutral: 'bg-chart-3',
  info: 'bg-info',
  brand: 'bg-brand',
  success: 'bg-success',
  warning: 'bg-chart-5',
  danger: 'bg-danger',
};

/** Workload breakdowns and the creation trend, stacked in the narrow column. */
export default function DashboardSideColumn({ tasks }: DashboardSideColumnProps) {
  const { statuses, priorities, statusTone, priorityTone } = useTaskLookups();
  const { byStatus, byPriority } = countTasks(tasks);

  const statusSegments: BreakdownSegment[] = statuses.map((s) => ({
    key: s.key,
    label: s.name,
    value: byStatus.get(s.key) ?? 0,
    colorClass: TONE_BAR[statusTone(s.key)],
  }));
  // Most important first
  const prioritySegments: BreakdownSegment[] = [...priorities].reverse().map((p) => ({
    key: p.key,
    label: p.name,
    value: byPriority.get(p.key) ?? 0,
    colorClass: TONE_BAR[priorityTone(p.key)],
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
