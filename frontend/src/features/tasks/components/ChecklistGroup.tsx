import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import { arrayMove, SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable';
import type { ChecklistItem } from '@/types/api';
import type { ChecklistControl } from '../hooks/useTaskChecklist';
import ChecklistItemRow from './ChecklistItemRow';

interface ChecklistGroupProps {
  /** Shown when the task has more than one checklist. */
  title?: string;
  items: ChecklistItem[];
  canEdit: boolean;
  checklist: ChecklistControl;
}

/** One checklist's items, reordered by dragging. */
export default function ChecklistGroup({ title, items, canEdit, checklist }: ChecklistGroupProps) {
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  const onDragEnd = ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) return;
    const ids = items.map((i) => i.id);
    const order = arrayMove(ids, ids.indexOf(Number(active.id)), ids.indexOf(Number(over.id)));
    const moved = items.find((i) => i.id === active.id);
    const position = order.indexOf(Number(active.id));
    if (moved) checklist.move(moved, position === 0 ? null : order[position - 1], order);
  };

  return (
    <div className="space-y-1">
      {title && <h3 className="text-xs font-medium text-muted-foreground">{title}</h3>}
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
        <SortableContext items={items.map((i) => i.id)} strategy={verticalListSortingStrategy}>
          <ul>
            {items.map((item) => (
              <ChecklistItemRow key={item.id} item={item} canEdit={canEdit} checklist={checklist} />
            ))}
          </ul>
        </SortableContext>
      </DndContext>
    </div>
  );
}
