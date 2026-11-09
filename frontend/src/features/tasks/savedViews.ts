import { z } from 'zod';
import { isTasksView, type TasksView } from '@/components/data/ViewToggle';
import { createJsonPreference } from '@/lib/preferences/jsonPreference';

/** A named set of filters, sort and view the user can come back to in one click. */
export interface SavedView {
  id: string;
  name: string;
  /** Filters and sort as a URL query string (what the tasks page keeps in its address). */
  query: string;
  view: TasksView;
}

const savedViewsSchema = z.array(
  z.object({
    id: z.string(),
    name: z.string(),
    query: z.string(),
    view: z.custom<TasksView>(isTasksView),
  })
);

/** Saved in this browser; views are personal shortcuts, not shared data. */
export const savedViewsStore = createJsonPreference('tasks-saved-views', savedViewsSchema, []);

export const saveViewSchema = z.object({
  name: z.string().trim().min(1, 'Give the view a name.').max(40, 'Keep the name under 40 characters.'),
});
export type SaveViewFormValues = z.infer<typeof saveViewSchema>;

export function addSavedView(name: string, query: string, view: TasksView): void {
  savedViewsStore.set((views) => [...views, { id: crypto.randomUUID(), name, query, view }]);
}

export function removeSavedView(id: string): void {
  savedViewsStore.set((views) => views.filter((v) => v.id !== id));
}
