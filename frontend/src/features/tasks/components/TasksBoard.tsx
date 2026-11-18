import { useState } from 'react';
import {
  DndContext,
  DragOverlay,
  KeyboardSensor,
  MouseSensor,
  TouchSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
  type UniqueIdentifier,
} from '@dnd-kit/core';
import useTaskLookups from '@/hooks/queries/useTaskLookups';
import { useAuth } from '@/lib/auth/AuthContext';
import { showToast } from '@/lib/toast/ToastContext';
import { cn } from '@/lib/utils';
import type { Task, TaskStatus } from '@/types/api';
import { boardAnnouncements } from '../boardAnnouncements';
import { assigneeFromLane, boardSettingsStore, laneKeyOf, lanesFor, sortTasks, type BoardSettings } from '../boardSettings';
import type { LaneChange, TasksBoardData } from '../hooks/useTasksBoard';
import BoardCell from './BoardCell';
import BoardColumnHeader from './BoardColumnHeader';
import BoardToolbar from './BoardToolbar';
import TaskCard from './TaskCard';

interface TasksBoardProps {
  board: TasksBoardData;
  /** Opens the full list filtered to one status, for columns with more cards than shown. */
  onShowAll: (status: TaskStatus) => void;
  /** Show each card's project, when the board spans several projects. */
  showProject?: boolean;
}

/** Cell ids are "<lane>|<status>" (BoardCell's cellId). */
const parseCell = (id: UniqueIdentifier | undefined): { lane: string; status: TaskStatus } | null => {
  if (typeof id !== 'string') return null;
  const [lane = '', status] = id.split('|');
  return status ? { lane, status } : null;
};

/** Kanban board: drag a card to another column (and lane) to change its status (and priority or assignee). */
export default function TasksBoard({ board, onShowAll, showProject = false }: TasksBoardProps) {
  const { can } = useAuth();
  const { priorityOptions, priorityRank, statusLabel } = useTaskLookups();
  const settings = boardSettingsStore.useValue();
  const canDrag = can('tasks.change_status') || can('tasks.update');
  const [activeTask, setActiveTask] = useState<Task | null>(null);

  const allTasks = board.columns.flatMap((c) => c.tasks);
  const lanes = lanesFor(allTasks, settings.group, priorityOptions);
  const grouped = settings.group !== 'none';
  const findTask = (id: UniqueIdentifier) => allTasks.find((t) => t.id === id);

  const sensors = useSensors(
    // A short distance keeps plain clicks (open the task) from starting a drag
    useSensor(MouseSensor, { activationConstraint: { distance: 6 } }),
    // Press and hold on touch, so the page can still scroll
    useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 6 } }),
    useSensor(KeyboardSensor)
  );

  const update = (change: Partial<BoardSettings>) => boardSettingsStore.set((prev) => ({ ...prev, ...change }));
  const toggleCollapsed = (status: TaskStatus) =>
    update({
      collapsed: settings.collapsed.includes(status)
        ? settings.collapsed.filter((s) => s !== status)
        : [...settings.collapsed, status],
    });

  /** The lane field a drop into another swimlane changes, when the user may change it. */
  const laneChangeFor = (task: Task, lane: string): LaneChange | undefined => {
    if (!grouped || lane === laneKeyOf(task, settings.group)) return undefined;
    if (settings.group === 'priority') {
      if (can('tasks.update')) return { priority: lane };
    } else if (can('tasks.assign')) {
      const id = assigneeFromLane(lane);
      return { assignee: allTasks.find((t) => t.assignee?.id === id)?.assignee ?? null };
    }
    showToast("You can't change that from the board; only the status was updated.", 'info');
    return undefined;
  };

  const handleDragEnd = ({ active, over }: DragEndEvent) => {
    setActiveTask(null);
    const task = findTask(active.id);
    const cell = parseCell(over?.id);
    if (task && cell) board.move(task, cell.status, laneChangeFor(task, cell.lane));
  };

  return (
    <DndContext
      sensors={sensors}
      accessibility={{ announcements: boardAnnouncements(findTask, statusLabel) }}
      onDragStart={({ active }) => setActiveTask(findTask(active.id) ?? null)}
      onDragEnd={handleDragEnd}
      onDragCancel={() => setActiveTask(null)}
    >
      <BoardToolbar
        sort={settings.sort}
        group={settings.group}
        onSortChange={(sort) => update({ sort })}
        onGroupChange={(group) => update({ group })}
      />
      <div className="overflow-x-auto p-3">
        <div className="flex min-w-max gap-2 lg:min-w-0">
          {board.columns.map((column) => {
            const collapsed = settings.collapsed.includes(column.status);
            const hidden = column.total - column.tasks.length;
            return (
              <section
                key={column.status}
                aria-label={`${statusLabel(column.status)} column`}
                className={cn('rounded-lg bg-muted/60 p-1.5', collapsed ? 'w-12 shrink-0' : 'min-w-60 flex-1')}
              >
                <BoardColumnHeader
                  status={column.status}
                  count={column.total}
                  limit={settings.limits[column.status]}
                  collapsed={collapsed}
                  onToggleCollapsed={() => toggleCollapsed(column.status)}
                  onLimitChange={(limit) => update({ limits: { ...settings.limits, [column.status]: limit } })}
                />
                {lanes.map((lane) => (
                  <div key={lane.key} className={cn(grouped && 'mt-2 border-t border-border/70 pt-1.5')}>
                    {grouped && !collapsed && (
                      <p className="truncate px-1.5 pb-1 text-[11px] font-medium text-muted-foreground uppercase">{lane.label}</p>
                    )}
                    <BoardCell
                      status={column.status}
                      laneKey={lane.key}
                      tasks={sortTasks(
                        column.tasks.filter((t) => !grouped || laneKeyOf(t, settings.group) === lane.key),
                        settings.sort,
                        priorityRank
                      )}
                      loading={column.isPending}
                      collapsed={collapsed}
                      canDrag={canDrag}
                      showProject={showProject}
                      isDropTarget={activeTask !== null && activeTask.status !== column.status}
                    />
                  </div>
                ))}
                {column.isError && <p className="px-1.5 py-2 text-xs text-destructive">Couldn't load this column.</p>}
                {hidden > 0 && !collapsed && (
                  <button
                    type="button"
                    onClick={() => onShowAll(column.status)}
                    className="mt-1 w-full rounded-md px-2 py-1 text-xs text-muted-foreground transition-colors hover:bg-background hover:text-brand"
                  >
                    +{hidden} more in the list
                  </button>
                )}
              </section>
            );
          })}
        </div>
      </div>

      <DragOverlay dropAnimation={null}>
        {activeTask && <TaskCard task={activeTask} overlay showProject={showProject} />}
      </DragOverlay>
    </DndContext>
  );
}
