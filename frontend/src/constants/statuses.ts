import type { ProjectStatus, TaskPriority, TaskStatus } from '@/types/api';

// Must match the backend enums (StoreTaskRequest, StoreProjectRequest).

export const TASK_STATUS_LABELS: Record<TaskStatus, string> = {
  todo: 'To Do',
  in_progress: 'In Progress',
  review: 'Review',
  completed: 'Completed',
};

export const TASK_PRIORITY_LABELS: Record<TaskPriority, string> = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  urgent: 'Urgent',
};

export const PROJECT_STATUS_LABELS: Record<ProjectStatus, string> = {
  active: 'Active',
  in_progress: 'In Progress',
  on_hold: 'On Hold',
  completed: 'Completed',
  archived: 'Archived',
};

export const TASK_STATUSES: TaskStatus[] = ['todo', 'in_progress', 'review', 'completed'];
export const TASK_PRIORITIES: TaskPriority[] = ['low', 'medium', 'high', 'urgent'];

/** Statuses a form may set; `archived` only changes through the archive toggle. */
export const EDITABLE_PROJECT_STATUSES: Exclude<ProjectStatus, 'archived'>[] = [
  'active',
  'in_progress',
  'on_hold',
  'completed',
];

/** Colour family of a badge: soft background + strong text in the same hue. */
export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'brand' | 'neutral';

export const STATUS_TONES: Record<ProjectStatus | TaskStatus, Tone> = {
  active: 'success',
  in_progress: 'info',
  review: 'brand',
  on_hold: 'warning',
  todo: 'neutral',
  completed: 'success',
  archived: 'neutral',
};

export const PRIORITY_TONES: Record<TaskPriority, Tone> = {
  low: 'neutral',
  medium: 'info',
  high: 'warning',
  urgent: 'danger',
};

/** Statuses that are "live" and get a pinging dot. */
export function isLiveStatus(status: ProjectStatus | TaskStatus): boolean {
  return status === 'in_progress';
}
