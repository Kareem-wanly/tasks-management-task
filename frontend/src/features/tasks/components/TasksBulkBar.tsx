import { useState } from 'react';
import { motion } from 'motion/react';
import {
  CalendarPlusIcon,
  CheckIcon,
  CircleDotIcon,
  Loader2Icon,
  SignalIcon,
  Trash2Icon,
  UserRoundIcon,
  XIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import ConfirmModal from '@/components/ui/ConfirmModal';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { TASK_PRIORITIES, TASK_PRIORITY_LABELS, TASK_STATUSES, TASK_STATUS_LABELS } from '@/constants/statuses';
import { useAuth } from '@/lib/auth/AuthContext';
import { EASE } from '@/lib/motion';
import type { Task } from '@/types/api';
import useBulkTaskActions, { type BulkAction } from '../hooks/useBulkTaskActions';

interface TasksBulkBarProps {
  selected: Task[];
  onClear: () => void;
}

const EXTEND_DAYS = [1, 3, 7, 14];

/** Floating action bar for the selected tasks: status, priority, due date, assignee, delete. */
export default function TasksBulkBar({ selected, onClear }: TasksBulkBarProps) {
  const { can, user } = useAuth();
  const [confirmDelete, setConfirmDelete] = useState(false);
  const bulk = useBulkTaskActions(() => {
    setConfirmDelete(false);
    onClear();
  });
  const run = (action: BulkAction) => bulk.mutate({ tasks: selected, action });
  const canEdit = can('tasks.update');

  return (
    <>
      <motion.div
        role="toolbar"
        aria-label={`${selected.length} selected tasks`}
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.2, ease: EASE }}
        className="fixed inset-x-3 bottom-20 z-40 mx-auto flex max-w-fit flex-wrap items-center gap-1 rounded-lg border border-border bg-popover p-1.5 shadow-lift md:bottom-6"
      >
        <span className="px-2 text-[13px] font-medium tabular-nums">{selected.length} selected</span>
        {bulk.isPending && <Loader2Icon className="size-4 animate-spin text-muted-foreground" aria-label="Working" />}

        {(can('tasks.change_status') || canEdit) && (
          <>
            <Button size="sm" variant="ghost" disabled={bulk.isPending} onClick={() => run({ kind: 'status', status: 'completed' })}>
              <CheckIcon />
              Complete
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button size="sm" variant="ghost" disabled={bulk.isPending}>
                  <CircleDotIcon />
                  Status
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="center" side="top">
                {TASK_STATUSES.map((status) => (
                  <DropdownMenuItem key={status} onSelect={() => run({ kind: 'status', status })}>
                    {TASK_STATUS_LABELS[status]}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          </>
        )}

        {canEdit && (
          <>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button size="sm" variant="ghost" disabled={bulk.isPending}>
                  <SignalIcon />
                  Priority
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="center" side="top">
                {TASK_PRIORITIES.map((priority) => (
                  <DropdownMenuItem key={priority} onSelect={() => run({ kind: 'priority', priority })}>
                    {TASK_PRIORITY_LABELS[priority]}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button size="sm" variant="ghost" disabled={bulk.isPending}>
                  <CalendarPlusIcon />
                  Due
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="center" side="top">
                {EXTEND_DAYS.map((days) => (
                  <DropdownMenuItem key={days} onSelect={() => run({ kind: 'extend', days })}>
                    Push back {days} {days === 1 ? 'day' : 'days'}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          </>
        )}

        {can('tasks.assign') && user && (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button size="sm" variant="ghost" disabled={bulk.isPending}>
                <UserRoundIcon />
                Assignee
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="center" side="top">
              <DropdownMenuItem onSelect={() => run({ kind: 'assign', userId: user.id })}>Assign to me</DropdownMenuItem>
              <DropdownMenuItem onSelect={() => run({ kind: 'assign', userId: null })}>Unassign</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        )}

        {can('tasks.delete') && (
          <Button
            size="sm"
            variant="ghost"
            className="text-destructive hover:text-destructive"
            disabled={bulk.isPending}
            onClick={() => setConfirmDelete(true)}
          >
            <Trash2Icon />
            Delete
          </Button>
        )}

        <Button size="icon-sm" variant="ghost" onClick={onClear} aria-label="Clear selection">
          <XIcon />
        </Button>
      </motion.div>

      <ConfirmModal
        isOpen={confirmDelete}
        onClose={() => setConfirmDelete(false)}
        onConfirm={() => run({ kind: 'delete' })}
        title={`Delete ${selected.length} tasks`}
        message="These tasks and their comments will be permanently deleted. This can't be undone."
        confirmText="Delete tasks"
        danger
        loading={bulk.isPending}
      />
    </>
  );
}
