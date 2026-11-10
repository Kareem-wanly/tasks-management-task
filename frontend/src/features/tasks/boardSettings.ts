import { z } from 'zod';
import type { LookupOption } from '@/hooks/queries/useTaskLookups';
import { createJsonPreference } from '@/lib/preferences/jsonPreference';
import type { Task, TaskPriority } from '@/types/api';
import { daysUntil } from '@/utils/dates';

export const BOARD_SORTS = { newest: 'Newest first', due: 'Due date', priority: 'Priority' } as const;
export type BoardSort = keyof typeof BOARD_SORTS;

export const BOARD_GROUPS = { none: 'No grouping', priority: 'Priority', assignee: 'Assignee' } as const;
export type BoardGroup = keyof typeof BOARD_GROUPS;

// Status keys; one that no longer exists just never matches a column
const statusSchema = z.string();

const settingsSchema = z.object({
  sort: z.enum(['newest', 'due', 'priority']),
  group: z.enum(['none', 'priority', 'assignee']),
  collapsed: z.array(statusSchema),
  /** Work-in-progress limits per column; a column over its limit is flagged. */
  limits: z.partialRecord(statusSchema, z.number().int().positive()),
});

export type BoardSettings = z.infer<typeof settingsSchema>;

/** Personal board layout, kept in this browser. */
export const boardSettingsStore = createJsonPreference('board-settings', settingsSchema, {
  sort: 'newest',
  group: 'none',
  collapsed: [],
  limits: {},
});

/** `priorityRank`: higher = more important (useTaskLookups). */
export function sortTasks(tasks: Task[], sort: BoardSort, priorityRank: (key: TaskPriority) => number): Task[] {
  if (sort === 'newest') return tasks;
  const due = (t: Task) => daysUntil(t.due_date) ?? Number.MAX_SAFE_INTEGER;
  const rank = (t: Task) => priorityRank(t.priority);
  return [...tasks].sort((a, b) => (sort === 'due' ? due(a) - due(b) : rank(b) - rank(a) || due(a) - due(b)));
}

export interface Lane {
  key: string;
  label: string;
}

const UNASSIGNED = 'unassigned';

export function laneKeyOf(task: Task, group: BoardGroup): string {
  if (group === 'priority') return task.priority;
  if (group === 'assignee') return task.assignee ? `user:${task.assignee.id}` : UNASSIGNED;
  return 'all';
}

/** Lanes for the loaded tasks: priorities high → low, or people A → Z with "Unassigned" last. */
export function lanesFor(tasks: Task[], group: BoardGroup, priorities: LookupOption[]): Lane[] {
  if (group === 'none') return [{ key: 'all', label: '' }];
  if (group === 'priority') {
    return [...priorities].reverse().map((p) => ({ key: p.value, label: p.label }));
  }
  const people = new Map<string, string>();
  for (const task of tasks) if (task.assignee) people.set(`user:${task.assignee.id}`, task.assignee.name);
  const lanes = [...people].map(([key, label]) => ({ key, label })).sort((a, b) => a.label.localeCompare(b.label));
  return [...lanes, { key: UNASSIGNED, label: 'Unassigned' }];
}

/** Assignee id encoded in an assignee lane key (null for "Unassigned"). */
export function assigneeFromLane(key: string): number | null {
  return key.startsWith('user:') ? Number(key.slice(5)) : null;
}
