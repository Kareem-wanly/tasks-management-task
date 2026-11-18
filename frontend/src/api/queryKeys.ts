import type { QueryParams } from '@/lib/paginate';

/**
 * React Query keys, one factory per resource. Keys are nested, so invalidating
 * `queryKeys.users.all` also refreshes every `queryKeys.users.list(...)`.
 */
export const queryKeys = {
  users: {
    all: ['users'] as const,
    list: (params: QueryParams) => ['users', 'list', params] as const,
    options: ['users', 'options'] as const,
  },
  roles: {
    all: ['roles'] as const,
    detail: (id: number | string) => ['roles', 'detail', String(id)] as const,
  },
  permissions: {
    all: ['permissions'] as const,
  },
  projects: {
    all: ['projects'] as const,
    /** Every project list page; invalidate this (not `all`) after a delete, so the deleted detail isn't refetched. */
    lists: ['projects', 'list'] as const,
    list: (params: QueryParams) => ['projects', 'list', params] as const,
    detail: (id: number | string) => ['projects', 'detail', String(id)] as const,
    options: (includeArchived: boolean) => ['projects', 'options', { includeArchived }] as const,
    allOptions: ['projects', 'options'] as const,
    activities: (id: number | string, page: number) => ['projects', 'activities', String(id), page] as const,
  },
  activities: {
    all: ['activities'] as const,
    recent: (limit: number) => ['activities', 'recent', limit] as const,
  },
  taskLookups: {
    statuses: ['task-lookups', 'statuses'] as const,
    priorities: ['task-lookups', 'priorities'] as const,
  },
  tasks: {
    all: ['tasks'] as const,
    lists: ['tasks', 'list'] as const,
    list: (params: QueryParams) => ['tasks', 'list', params] as const,
    detail: (id: number | string) => ['tasks', 'detail', String(id)] as const,
  },
};
