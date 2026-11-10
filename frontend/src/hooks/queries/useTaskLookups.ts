import { useQuery } from '@tanstack/react-query';
import taskLookupsApi from '@/api/taskLookupsApi';
import { queryKeys } from '@/api/queryKeys';
import { TASK_DONE_STATUS, isTone, type Tone } from '@/constants/statuses';
import type { TaskPriority, TaskPriorityOption, TaskStatus, TaskStatusOption } from '@/types/api';

export interface LookupOption {
  value: string;
  label: string;
}

export interface TaskLookups {
  /** Ordered by `sort`; empty until loaded. */
  statuses: TaskStatusOption[];
  priorities: TaskPriorityOption[];
  statusOptions: LookupOption[];
  priorityOptions: LookupOption[];
  statusLabel: (key: TaskStatus) => string;
  priorityLabel: (key: TaskPriority) => string;
  statusTone: (key: TaskStatus) => Tone;
  priorityTone: (key: TaskPriority) => Tone;
  priorityOf: (key: TaskPriority) => TaskPriorityOption | undefined;
  /** Whether the status closes the task (is_final), not just "completed". */
  isFinal: (key: TaskStatus) => boolean;
  /** Position in the priority order (higher = more important); -1 when unknown. */
  priorityRank: (key: TaskPriority) => number;
  isPending: boolean;
}

/** "in_progress" → "In progress": a readable label until the lists load, or for a deleted value. */
const humanize = (key: string): string => {
  const words = key.replace(/_/g, ' ');
  return words.charAt(0).toUpperCase() + words.slice(1);
};

const toneOf = (row: TaskPriorityOption | undefined): Tone => (row && isTone(row.color) ? row.color : 'neutral');

const toOptions = (rows: TaskPriorityOption[]): LookupOption[] => rows.map((r) => ({ value: r.key, label: r.name }));

/**
 * Task statuses and priorities from the API, with lookups by key. Shared by every
 * feature that shows or edits a task; the lists rarely change, so they stay cached.
 */
export default function useTaskLookups(): TaskLookups {
  const statusesQuery = useQuery({
    queryKey: queryKeys.taskLookups.statuses,
    queryFn: ({ signal }) => taskLookupsApi.getStatuses({ signal }),
    staleTime: 10 * 60_000,
  });
  const prioritiesQuery = useQuery({
    queryKey: queryKeys.taskLookups.priorities,
    queryFn: ({ signal }) => taskLookupsApi.getPriorities({ signal }),
    staleTime: 10 * 60_000,
  });

  const statuses = statusesQuery.data ?? [];
  const priorities = prioritiesQuery.data ?? [];
  const status = (key: TaskStatus) => statuses.find((s) => s.key === key);
  const priorityOf = (key: TaskPriority) => priorities.find((p) => p.key === key);

  return {
    statuses,
    priorities,
    statusOptions: toOptions(statuses),
    priorityOptions: toOptions(priorities),
    statusLabel: (key) => status(key)?.name ?? humanize(key),
    priorityLabel: (key) => priorityOf(key)?.name ?? humanize(key),
    statusTone: (key) => toneOf(status(key)),
    priorityTone: (key) => toneOf(priorityOf(key)),
    priorityOf,
    // Before the list loads, fall back to the built-in closing status
    isFinal: (key) => (statusesQuery.data ? (status(key)?.is_final ?? false) : key === TASK_DONE_STATUS),
    priorityRank: (key) => priorities.findIndex((p) => p.key === key),
    isPending: statusesQuery.isPending || prioritiesQuery.isPending,
  };
}
