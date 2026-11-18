import { useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import checklistApi from '@/api/checklistApi';
import { queryKeys } from '@/api/queryKeys';
import useApiMutation from '@/hooks/useApiMutation';
import type { Checklist, ChecklistItem } from '@/types/api';

type ChecklistChange =
  | { kind: 'add'; title: string; listId?: number }
  | { kind: 'rename'; item: ChecklistItem; title: string }
  | { kind: 'toggle'; item: ChecklistItem; done: boolean }
  | { kind: 'remove'; item: ChecklistItem }
  | { kind: 'move'; item: ChecklistItem; afterId: number | null; order: number[] };

export interface ChecklistControl {
  query: UseQueryResult<Checklist>;
  add: (title: string, listId?: number, onDone?: () => void) => void;
  rename: (item: ChecklistItem, title: string) => void;
  toggle: (item: ChecklistItem, done: boolean) => void;
  remove: (item: ChecklistItem) => void;
  /** `order` is the list's item IDs in their new order, shown at once while Bitrix catches up. */
  move: (item: ChecklistItem, afterId: number | null, order: number[]) => void;
  isPending: boolean;
}

/** What a change looks like before the server answers, so ticks and drags feel instant. */
function optimistic(checklist: Checklist, change: ChecklistChange): Checklist {
  if (change.kind === 'toggle') {
    return {
      ...checklist,
      items: checklist.items.map((i) => (i.id === change.item.id ? { ...i, is_complete: change.done } : i)),
    };
  }
  if (change.kind === 'move') {
    return {
      ...checklist,
      items: checklist.items.map((i) => {
        const position = change.order.indexOf(i.id);
        return position === -1 ? i : { ...i, sort_index: position + 1 };
      }),
    };
  }
  return checklist;
}

/** A task's checklists (Bitrix24 step 4.3): read on open, every change answered with the whole list. */
export default function useTaskChecklist(taskId: number): ChecklistControl {
  const queryClient = useQueryClient();
  const key = queryKeys.tasks.checklist(taskId);

  const query = useQuery({
    queryKey: key,
    queryFn: ({ signal }) => checklistApi.get(taskId, { signal }),
  });

  const mutation = useApiMutation({
    mutationFn: async (change: ChecklistChange & { onDone?: () => void }) => {
      const previous = queryClient.getQueryData<Checklist>(key);
      if (previous) queryClient.setQueryData<Checklist>(key, optimistic(previous, change));
      try {
        switch (change.kind) {
          case 'add':
            return await checklistApi.add(taskId, change.title, change.listId);
          case 'rename':
            return await checklistApi.update(change.item.id, { title: change.title });
          case 'toggle':
            return await checklistApi.update(change.item.id, { is_complete: change.done });
          case 'remove':
            return await checklistApi.remove(change.item.id);
          case 'move':
            return await checklistApi.move(change.item.id, change.afterId);
        }
      } catch (err) {
        if (previous) queryClient.setQueryData<Checklist>(key, previous);
        throw err;
      }
    },
    errorMessage: 'Failed to update the checklist.',
    onSuccess: (checklist, change) => {
      queryClient.setQueryData<Checklist>(key, checklist);
      change.onDone?.();
    },
  });

  return {
    query,
    add: (title, listId, onDone) => mutation.mutate({ kind: 'add', title, listId, onDone }),
    rename: (item, title) => mutation.mutate({ kind: 'rename', item, title }),
    toggle: (item, done) => mutation.mutate({ kind: 'toggle', item, done }),
    remove: (item) => mutation.mutate({ kind: 'remove', item }),
    move: (item, afterId, order) => mutation.mutate({ kind: 'move', item, afterId, order }),
    isPending: mutation.isPending,
  };
}
