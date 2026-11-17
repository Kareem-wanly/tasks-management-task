import type { ReactNode } from 'react';
import type { DraggableAttributes, DraggableSyntheticListeners } from '@dnd-kit/core';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { cn } from '@/lib/utils';

/** Spread both on the element that starts the drag (a handle button). */
export interface SortableHandleProps {
  attributes: DraggableAttributes;
  listeners: DraggableSyntheticListeners;
}

interface SortableItemProps {
  id: number;
  disabled?: boolean;
  className?: string;
  /** Receives the handle props; the whole row moves with the drag. */
  children: (handle: SortableHandleProps) => ReactNode;
}

/** A list row that can be dragged within a dnd-kit SortableContext. */
export default function SortableItem({ id, disabled = false, className, children }: SortableItemProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id, disabled });

  return (
    <li
      ref={setNodeRef}
      // dnd-kit positions the rows while dragging; a computed transform has no class.
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(className, isDragging && 'relative z-10 rounded-md bg-muted shadow-sm')}
    >
      {children({ attributes, listeners })}
    </li>
  );
}
