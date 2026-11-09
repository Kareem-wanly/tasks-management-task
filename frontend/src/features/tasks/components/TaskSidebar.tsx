import { Link } from 'react-router-dom';
import { UserCheckIcon } from 'lucide-react';
import DeadlineBadge from '@/components/data/DeadlineBadge';
import ExtendDueDateMenu from '@/components/data/ExtendDueDateMenu';
import ProjectMark from '@/components/data/ProjectMark';
import StatusBadge from '@/components/data/StatusBadge';
import UserAvatar from '@/components/data/UserAvatar';
import Panel from '@/components/layout/Panel';
import { Button } from '@/components/ui/button';
import {
  PRIORITY_TONES,
  STATUS_TONES,
  TASK_PRIORITIES,
  TASK_PRIORITY_LABELS,
  TASK_STATUSES,
  TASK_STATUS_LABELS,
  isLiveStatus,
} from '@/constants/statuses';
import { useAuth } from '@/lib/auth/AuthContext';
import { cn } from '@/lib/utils';
import { formatDateOnly, formatTimestampDate } from '@/utils/format';
import type { Task, User } from '@/types/api';
import type { TaskQuickUpdate } from '../hooks/useTaskQuickUpdate';
import type { TaskStatusControl } from '../hooks/useTaskStatus';
import DetailRow from './DetailRow';
import InlineSelect from './InlineSelect';

interface TaskSidebarProps {
  task: Task;
  /** Owner and members of the task's project: the possible assignees. */
  members: User[];
  status: TaskStatusControl;
  quick: TaskQuickUpdate;
}

const UNASSIGNED = 'none';
const STATUS_OPTIONS = TASK_STATUSES.map((s) => ({ value: s, label: TASK_STATUS_LABELS[s] }));
const PRIORITY_OPTIONS = TASK_PRIORITIES.map((p) => ({ value: p, label: TASK_PRIORITY_LABELS[p] }));

/** Task facts; each one the user may change is edited right here and saved on pick. */
export default function TaskSidebar({ task, members, status, quick }: TaskSidebarProps) {
  const { can, user } = useAuth();
  const canEdit = can('tasks.update');
  const canChangeStatus = can('tasks.change_status') || canEdit;
  const canAssign = can('tasks.assign');
  const done = task.status === 'completed';
  // The server flag doesn't know about an optimistic status change until the refetch
  const isOverdue = task.is_overdue && !done;
  const isMember = user !== null && members.some((m) => m.id === user.id);

  const assigneeOptions = [
    { value: UNASSIGNED, label: 'Unassigned' },
    ...members.map((m) => ({ value: String(m.id), label: m.name })),
  ];

  return (
    <Panel title="Details" className="self-start">
      <dl className="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-6 gap-y-3 text-[13px]">
        <DetailRow label="Status">
          {canChangeStatus ? (
            <InlineSelect
              value={task.status}
              options={STATUS_OPTIONS}
              ariaLabel="Task status"
              pending={status.isPending}
              onChange={(value) => {
                const next = TASK_STATUSES.find((s) => s === value);
                if (next) status.change(task, next);
              }}
            />
          ) : (
            <StatusBadge tone={STATUS_TONES[task.status]} live={isLiveStatus(task.status)}>
              {TASK_STATUS_LABELS[task.status]}
            </StatusBadge>
          )}
        </DetailRow>

        <DetailRow label="Priority">
          {canEdit ? (
            <InlineSelect
              value={task.priority}
              options={PRIORITY_OPTIONS}
              ariaLabel="Task priority"
              onChange={(value) => {
                const next = TASK_PRIORITIES.find((p) => p === value);
                if (next) quick.update(task, { priority: next }, `Priority set to ${TASK_PRIORITY_LABELS[next]}.`);
              }}
            />
          ) : (
            <StatusBadge tone={PRIORITY_TONES[task.priority]}>{TASK_PRIORITY_LABELS[task.priority]}</StatusBadge>
          )}
        </DetailRow>

        <DetailRow label="Assignee">
          <div className="flex min-w-0 flex-col items-start gap-1.5">
            {canAssign && members.length > 0 ? (
              <InlineSelect
                value={task.assignee ? String(task.assignee.id) : UNASSIGNED}
                options={assigneeOptions}
                ariaLabel="Task assignee"
                onChange={(value) => {
                  const member = members.find((m) => String(m.id) === value);
                  quick.assignTo(task, member?.id ?? null, member?.name);
                }}
              />
            ) : task.assignee ? (
              <span className="flex min-w-0 items-center gap-2">
                <UserAvatar user={task.assignee} />
                <span className="truncate">{task.assignee.name}</span>
              </span>
            ) : (
              <span className="text-muted-foreground">Unassigned</span>
            )}
            {canAssign && isMember && user && task.assignee?.id !== user.id && (
              <Button
                variant="link"
                size="xs"
                className="h-auto px-0"
                onClick={() => quick.assignTo(task, user.id)}
                disabled={quick.isPending}
              >
                <UserCheckIcon />
                Assign to me
              </Button>
            )}
          </div>
        </DetailRow>

        <DetailRow label="Project">
          {task.project ? (
            <Link
              to={`/projects/${task.project.id}?tab=tasks`}
              className="flex min-w-0 items-center gap-2 font-medium transition-colors hover:text-brand"
            >
              <ProjectMark projectId={task.project.id} size="sm" />
              <span className="truncate">{task.project.title}</span>
            </Link>
          ) : (
            <span className="text-muted-foreground">None</span>
          )}
        </DetailRow>

        <DetailRow label="Due date">
          <div className="flex flex-wrap items-center gap-2">
            <span className={cn('tabular-nums', isOverdue && 'font-medium text-danger')}>
              {formatDateOnly(task.due_date, 'No due date')}
            </span>
            <DeadlineBadge dueDate={task.due_date} overdue={isOverdue} done={done} />
            {canEdit && !done && (
              <ExtendDueDateMenu
                compact
                dueDate={task.due_date}
                onExtend={(dueDate) => quick.extend(task, dueDate)}
                pending={quick.isPending}
                urgent={isOverdue}
              />
            )}
          </div>
        </DetailRow>

        {task.creator && (
          <DetailRow label="Created by">
            <span className="flex min-w-0 items-center gap-2">
              <UserAvatar user={task.creator} size="xs" />
              <span className="truncate">{task.creator.name}</span>
            </span>
          </DetailRow>
        )}

        <DetailRow label="Created">
          <span className="tabular-nums">{formatTimestampDate(task.created_at)}</span>
        </DetailRow>

        {task.completed_at && (
          <DetailRow label="Completed">
            <span className="font-medium text-success tabular-nums">{formatTimestampDate(task.completed_at)}</span>
          </DetailRow>
        )}
      </dl>
    </Panel>
  );
}
