import type { MouseEvent } from 'react';
import { CheckIcon, CircleCheckBigIcon, CircleIcon, Loader2Icon, RotateCcwIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import useTaskStatus, { type StatusTask } from '../hooks/useTaskStatus';

interface TaskCompleteButtonProps {
  task: StatusTask;
  /** `icon` for table rows, `button` for the task page header. */
  variant?: 'icon' | 'button';
}

/** Marks a task completed in one click, or reopens it (back to To Do). Undo lives in the toast. */
export default function TaskCompleteButton({ task, variant = 'icon' }: TaskCompleteButtonProps) {
  const status = useTaskStatus();
  const isDone = task.status === 'completed';

  const toggle = (e: MouseEvent<HTMLButtonElement>) => {
    // Rows navigate on click; this button must not
    e.stopPropagation();
    status.change(task, isDone ? 'todo' : 'completed');
  };

  if (variant === 'button') {
    return (
      <Button variant={isDone ? 'outline' : 'brand'} onClick={toggle} disabled={status.isPending}>
        {status.isPending ? <Loader2Icon className="animate-spin" /> : isDone ? <RotateCcwIcon /> : <CheckIcon />}
        {isDone ? 'Reopen' : 'Mark complete'}
      </Button>
    );
  }

  const label = isDone ? `Reopen ${task.title}` : `Mark ${task.title} as completed`;
  const Icon = status.isPending ? Loader2Icon : isDone ? CircleCheckBigIcon : CircleIcon;

  return (
    <Button
      type="button"
      variant="ghost"
      size="icon-sm"
      onClick={toggle}
      disabled={status.isPending}
      aria-label={label}
      title={isDone ? 'Reopen task' : 'Mark as completed'}
      className={cn(isDone ?'text-success hover:text-success' : 'text-muted-foreground hover:text-success')}
    >
      <Icon className={cn(status.isPending && 'animate-spin')} />
    </Button>
  );
}
