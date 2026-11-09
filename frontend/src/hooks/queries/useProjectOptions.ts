import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import projectsApi from '@/api/projectsApi';
import { queryKeys } from '@/api/queryKeys';
import type { ProjectOption } from '@/types/api';

/**
 * Lightweight project list (id, title, status) for dropdowns.
 * Forms want active projects only; list filters pass `includeArchived: true`.
 */
export default function useProjectOptions(
  { includeArchived = false, enabled = true }: { includeArchived?: boolean; enabled?: boolean } = {}
): UseQueryResult<ProjectOption[]> {
  return useQuery({
    queryKey: queryKeys.projects.options(includeArchived),
    queryFn: ({ signal }) => projectsApi.options({ include_archived: includeArchived ? 1 : 0 }, { signal }),
    staleTime: 5 * 60_000,
    enabled,
  });
}
