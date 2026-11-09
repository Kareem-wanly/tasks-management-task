import { ChartAreaIcon } from 'lucide-react';
import AreaChart from '@/components/data/AreaChart';
import EmptyState from '@/components/data/EmptyState';
import Panel from '@/components/layout/Panel';
import type { Task } from '@/types/api';
import { dailySeries } from '../utils';

interface TasksCreatedPanelProps {
  tasks: Task[];
}

export default function TasksCreatedPanel({ tasks }: TasksCreatedPanelProps) {
  return (
    <Panel title="Tasks created" description="Last 30 days">
      {tasks.length === 0 ? (
        <EmptyState
          icon={ChartAreaIcon}
          title="No tasks yet"
          description="New tasks will show up here as they are created."
          className="py-10"
        />
      ) : (
        <AreaChart points={dailySeries(tasks)} unit="tasks" />
      )}
    </Panel>
  );
}
