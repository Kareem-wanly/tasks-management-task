import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { Checklist, ChecklistItem } from '@/types/api';

interface ChecklistBody {
  data: ChecklistItem[];
  meta: { stale: boolean; can_edit: boolean };
}

const toChecklist = (body: ChecklistBody): Checklist => ({
  items: body.data,
  stale: body.meta.stale,
  can_edit: body.meta.can_edit,
});

/** A task's checklists (Bitrix24 step 4.3). Every call answers with the whole checklist. */
const checklistApi = {
  /** Fetched from Bitrix24 on each call: it sends no events for checklists. */
  get: async (taskId: number, options: RequestOptions = {}) =>
    toChecklist(await apiClient.get<ChecklistBody>(`/tasks/${taskId}/checklist`, options)),

  /** Adds to the given list, or to the first one. */
  add: async (taskId: number, title: string, listId?: number) =>
    toChecklist(await apiClient.post<ChecklistBody>(`/tasks/${taskId}/checklist`, { title, list_id: listId ?? null })),

  update: async (itemId: number, changes: { title?: string; is_complete?: boolean }) =>
    toChecklist(await apiClient.patch<ChecklistBody>(`/checklist-items/${itemId}`, { ...changes })),

  remove: async (itemId: number) => toChecklist(await apiClient.delete<ChecklistBody>(`/checklist-items/${itemId}`)),

  /** Right after `afterId` in the same list; null puts it first. */
  move: async (itemId: number, afterId: number | null) =>
    toChecklist(await apiClient.post<ChecklistBody>(`/checklist-items/${itemId}/move`, { after_id: afterId })),
};

export default checklistApi;
