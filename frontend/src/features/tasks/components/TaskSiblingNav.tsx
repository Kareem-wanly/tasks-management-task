import { Link } from 'react-router-dom';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import useTaskSiblings from '../hooks/useTaskSiblings';

interface TaskSiblingNavProps {
  projectId: number | undefined;
  taskId: number;
}

/** "‹ 3 / 12 ›": walk through a project's tasks without going back to the list. */
export default function TaskSiblingNav({ projectId, taskId }: TaskSiblingNavProps) {
  const { previousId, nextId, position, count } = useTaskSiblings(projectId, taskId);
  if (position === null || count < 2) return null;

  const arrow = (id: number | null, label: string, Icon: typeof ChevronLeftIcon) =>
    id === null ? (
      <Button variant="ghost" size="icon-sm" disabled aria-label={label}>
        <Icon className="rtl:-scale-x-100" />
      </Button>
    ) : (
      <Button variant="ghost" size="icon-sm" asChild>
        <Link to={`/tasks/${id}`} aria-label={label} title={label}>
          <Icon className="rtl:-scale-x-100" />
        </Link>
      </Button>
    );

  return (
    <div className="inline-flex items-center gap-1 rounded-xl border bg-card p-0.5" aria-label="Project tasks">
      {arrow(previousId, 'Previous task', ChevronLeftIcon)}
      <span className="px-1 text-xs text-muted-foreground tabular-nums">
        {position} / {count}
      </span>
      {arrow(nextId, 'Next task', ChevronRightIcon)}
    </div>
  );
}
