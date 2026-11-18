import { Link } from 'react-router-dom';
import TaskStatusBadge from '@/components/data/TaskStatusBadge';
import UserAvatar from '@/components/data/UserAvatar';
import Panel from '@/components/layout/Panel';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import { useAuth } from '@/lib/auth/AuthContext';
import { cn } from '@/lib/utils';
import type { Task } from '@/types/api';
import { formatDateOnly } from '@/utils/format';
import SubtaskComposer from './SubtaskComposer';

interface TaskSubtasksProps {
  task: Task;
}

/** The smaller tasks this one is split into (Bitrix24 step 4.2). */
export default function TaskSubtasks({ task }: TaskSubtasksProps) {
  const { can } = useAuth();
  const { isFinal } = useTaskLookups();
  const subtasks = task.subtasks ?? [];
  const done = subtasks.filter((s) => isFinal(s.status)).length;
  const canAdd = can('tasks.create');

  if (subtasks.length === 0 && !canAdd) return null;

  return (
    <Panel
      title="Subtasks"
      description={subtasks.length > 0 ? `${done} of ${subtasks.length} done` : undefined}
      bodyClassName="space-y-3"
    >
      {subtasks.length > 0 && (
        <ul className="divide-y divide-border">
          {subtasks.map((subtask) => (
            <li key={subtask.id} className="flex min-w-0 items-center gap-3 py-2 text-[13px]">
              <TaskStatusBadge status={subtask.status} />
              <Link
                to={`/tasks/${subtask.id}`}
                className={cn(
                  'min-w-0 flex-1 truncate font-medium transition-colors hover:text-brand',
                  isFinal(subtask.status) && 'text-muted-foreground line-through'
                )}
              >
                {subtask.title}
              </Link>
              {subtask.due_date && (
                <span className={cn('shrink-0 tabular-nums text-muted-foreground', subtask.is_overdue && 'text-danger')}>
                  {formatDateOnly(subtask.due_date)}
                </span>
              )}
              {subtask.assignee && <UserAvatar user={subtask.assignee} size="xs" />}
            </li>
          ))}
        </ul>
      )}
      {canAdd && <SubtaskComposer parent={task} />}
    </Panel>
  );
}
