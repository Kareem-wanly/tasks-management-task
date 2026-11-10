import { isLiveStatus } from '@/constants/statuses';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import type { TaskStatus } from '@/types/api';
import StatusBadge from './StatusBadge';

interface TaskStatusBadgeProps {
  status: TaskStatus;
  className?: string;
}

/** A task status as a badge, named and coloured from /task-statuses. */
export default function TaskStatusBadge({ status, className }: TaskStatusBadgeProps) {
  const { statusLabel, statusTone } = useTaskLookups();

  return (
    <StatusBadge tone={statusTone(status)} live={isLiveStatus(status)} className={className}>
      {statusLabel(status)}
    </StatusBadge>
  );
}
