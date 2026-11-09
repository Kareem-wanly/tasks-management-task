import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import usersApi from '@/api/usersApi';
import { queryKeys } from '@/api/queryKeys';
import type { UserOption } from '@/types/api';

/**
 * Lightweight user list (id, name, email) for dropdowns. Needs `users.view`,
 * so pass `enabled: can('users.view')` to avoid a 403.
 */
export default function useUserOptions({ enabled = true }: { enabled?: boolean } = {}): UseQueryResult<UserOption[]> {
  return useQuery({
    queryKey: queryKeys.users.options,
    queryFn: ({ signal }) => usersApi.options({ signal }),
    staleTime: 5 * 60_000,
    enabled,
  });
}
