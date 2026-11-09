import { useDroppable } from '@dnd-kit/core';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { Task, TaskStatus } from '@/types/api';
import BoardCard from './BoardCard';

interface BoardCellProps {
  status: TaskStatus;
  /** Swimlane this cell belongs to ('all' without grouping). */
  laneKey: string;
  tasks: Task[];
  loading: boolean;
  collapsed: boolean;
  canDrag: boolean;
  showProject: boolean;
  /** A card is being dragged and could land here. */
  isDropTarget: boolean;
}

/** Encodes the cell a card was dropped on: "<lane>|<status>". */
export const cellId = (laneKey: string, status: TaskStatus): string => `${laneKey}|${status}`;

/** The droppable list of cards for one status (within one swimlane). */
export default function BoardCell({
  status,
  laneKey,
  tasks,
  loading,
  collapsed,
  canDrag,
  showProject,
  isDropTarget,
}: BoardCellProps) {
  const { setNodeRef, isOver } = useDroppable({ id: cellId(laneKey, status) });

  return (
    <div
      ref={setNodeRef}
      className={cn(
        'flex min-h-24 flex-1 flex-col gap-1.5 rounded-md border border-transparent p-1 transition-colors duration-150',
        isDropTarget && 'border-dashed border-border',
        isOver && 'border-solid border-brand/60 bg-brand-soft/60'
      )}
    >
      {collapsed ? (
        tasks.length > 0 && <span className="text-center text-xs text-muted-foreground tabular-nums">{tasks.length}</span>
      ) : loading ? (
        Array.from({ length: 2 }, (_, i) => <Skeleton key={i} className="h-20 rounded-lg" />)
      ) : tasks.length === 0 ? (
        <p className="flex flex-1 items-center justify-center rounded-md border border-dashed border-border px-3 py-6 text-center text-xs text-muted-foreground">
          {canDrag ? 'Drop here' : 'No tasks'}
        </p>
      ) : (
        tasks.map((task) => <BoardCard key={task.id} task={task} disabled={!canDrag} showProject={showProject} />)
      )}
    </div>
  );
}
