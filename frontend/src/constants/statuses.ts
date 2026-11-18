import type { ProjectStatus } from '@/types/api';

// Project statuses must match StoreProjectRequest. Task statuses and priorities are data
// (GET /task-statuses, /task-priorities): read them with useTaskLookups, not from here.

export const PROJECT_STATUS_LABELS: Record<ProjectStatus, string> = {
  active: 'Active',
  in_progress: 'In Progress',
  on_hold: 'On Hold',
  completed: 'Completed',
  archived: 'Archived',
};

/** Statuses a form may set; `archived` only changes through the archive toggle. */
export const EDITABLE_PROJECT_STATUSES: Exclude<ProjectStatus, 'archived'>[] = [
  'active',
  'in_progress',
  'on_hold',
  'completed',
];

/** Built-in task keys some actions use directly ("Complete", "Reopen", the "Urgent" chip). */
export const TASK_DONE_STATUS = 'completed';
export const TASK_REOPEN_STATUS = 'todo';
export const TASK_DEFAULT_PRIORITY = 'medium';
export const URGENT_PRIORITY = 'urgent';

/** Colour family of a badge: soft background + strong text in the same hue. */
export const TONES = ['success', 'warning', 'danger', 'info', 'brand', 'neutral'] as const;
export type Tone = (typeof TONES)[number];

export const isTone = (value: string | null): value is Tone => TONES.some((t) => t === value);

export const PROJECT_STATUS_TONES: Record<ProjectStatus, Tone> = {
  active: 'success',
  in_progress: 'info',
  on_hold: 'warning',
  completed: 'success',
  archived: 'neutral',
};

/** Statuses that are "live" and get a pinging dot. */
export function isLiveStatus(status: string): boolean {
  return status === 'in_progress';
}
