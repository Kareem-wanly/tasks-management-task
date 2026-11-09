import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import projectsApi from '@/api/projectsApi';
import { queryKeys } from '@/api/queryKeys';
import type { Project } from '@/types/api';

/**
 * One project with owner, members and task counts. Shared by the project page and the
 * task form (which needs the members for the assignee list); both read the same cache entry.
 */
export default function useProject(id: number | string | null | undefined): UseQueryResult<Project> {
  const key = id ? String(id) : '';
  return useQuery({
    queryKey: queryKeys.projects.detail(key),
    queryFn: ({ signal }) => projectsApi.getById(key, { signal }),
    enabled: key !== '',
  });
}
