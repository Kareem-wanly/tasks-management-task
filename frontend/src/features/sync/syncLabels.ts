import type { BitrixSyncFailure, BitrixSyncState } from '@/types/api';

type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export const SYNC_ENTITY_LABELS: Record<string, string> = {
  users: 'Employees',
  projects: 'Projects',
  tasks: 'Tasks',
  checklists: 'Checklists',
  time: 'Time logs',
  files: 'Files',
  results: 'Results',
  chat: 'Task chats',
  'tasks-changes': 'Changes (every 5 minutes)',
};

export const SYNC_STATE_LABELS: Record<BitrixSyncState, string> = {
  never: 'Never synced',
  running: 'Running',
  completed: 'Up to date',
  failed: 'Failed',
  stalled: 'Stopped',
};

export const SYNC_STATE_TONES: Record<BitrixSyncState, BadgeVariant> = {
  never: 'neutral',
  running: 'info',
  completed: 'success',
  failed: 'danger',
  stalled: 'warning',
};

/** What failed, in words: "Task 123 (event)", "Import: Tasks". Bitrix IDs, as the subject holds them. */
export function failureLabel(failure: BitrixSyncFailure): string {
  switch (failure.operation) {
    case 'task':
      return `Task ${failure.subject} (Bitrix ID)`;
    case 'project':
      return `Project group ${failure.subject} (Bitrix ID)`;
    case 'task_chat':
      return `Chat of task ${failure.subject} (Bitrix ID)`;
    case 'import':
      return `Import: ${SYNC_ENTITY_LABELS[failure.subject] ?? failure.subject}`;
  }
}
