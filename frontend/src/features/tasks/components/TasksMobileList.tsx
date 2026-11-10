import { Link } from 'react-router-dom';
import { InboxIcon } from 'lucide-react';
import DeadlineBadge from '@/components/data/DeadlineBadge';
import EmptyState from '@/components/data/EmptyState';
import ProjectMark from '@/components/data/ProjectMark';
import TaskStatusBadge from '@/components/data/TaskStatusBadge';
import UserAvatar from '@/components/data/UserAvatar';
import { Skeleton } from '@/components/ui/skeleton';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import { cn } from '@/lib/utils';
import type { Task } from '@/types/api';
import PriorityIcon from './PriorityIcon';
import TaskCompleteButton from './TaskCompleteButton';

interface TasksMobileListProps {
  tasks: Task[];
  loading: boolean;
  canChangeStatus: boolean;
  showProject: boolean;
  emptyMessage: string;
}

/** Phone layout of the tasks table: one compact row per task, nothing to scroll sideways. */
export default function TasksMobileList({ tasks, loading, canChangeStatus, showProject, emptyMessage }: TasksMobileListProps) {
  const { isFinal } = useTaskLookups();

  if (loading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 5 }, (_, i) => (
          <Skeleton key={i} className="h-16 rounded-lg" />
        ))}
      </div>
    );
  }

  if (tasks.length === 0) {
    return <EmptyState icon={InboxIcon} title={emptyMessage} className="py-10" />;
  }

  return (
    <ul className="divide-y divide-border rounded-lg border border-border bg-card">
      {tasks.map((task) => (
        <li key={task.id} className="flex items-start gap-2 p-3">
          {canChangeStatus && <TaskCompleteButton task={task} />}
          <div className="min-w-0 flex-1 space-y-1">
            <Link
              to={`/tasks/${task.id}`}
              className={cn('block text-[13px] leading-5 font-medium', isFinal(task.status) && 'text-muted-foreground line-through')}
            >
              {task.title}
            </Link>
            <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
              <PriorityIcon priority={task.priority} />
              <TaskStatusBadge status={task.status} className="px-1.5 py-0 text-[10px]" />
              <DeadlineBadge dueDate={task.due_date} overdue={task.is_overdue} done={isFinal(task.status)} className="px-1.5 py-0 text-[10px]" />
              {showProject && task.project && (
                <span className="flex min-w-0 items-center gap-1">
                  <ProjectMark projectId={task.project.id} size="xs" />
                  <span className="truncate">{task.project.title}</span>
                </span>
              )}
            </div>
          </div>
          {task.assignee && <UserAvatar user={task.assignee} size="sm" />}
        </li>
      ))}
    </ul>
  );
}
