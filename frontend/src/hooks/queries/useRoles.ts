import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import rolesApi from '@/api/rolesApi';
import { queryKeys } from '@/api/queryKeys';
import type { Role } from '@/types/api';

/** All roles with permission and user counts. Shared by the users and roles features. */
export default function useRoles({ enabled = true }: { enabled?: boolean } = {}): UseQueryResult<Role[]> {
  return useQuery({
    queryKey: queryKeys.roles.all,
    queryFn: ({ signal }) => rolesApi.getAll({ signal }),
    staleTime: 5 * 60_000,
    enabled,
  });
}
