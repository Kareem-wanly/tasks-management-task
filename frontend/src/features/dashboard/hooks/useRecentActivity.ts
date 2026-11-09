import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import activitiesApi from '@/api/activitiesApi';
import { queryKeys } from '@/api/queryKeys';
import type { ActivityLog, Page } from '@/types/api';

/** The latest changes across every project the user can see (GET /activities, needs activities.view). */
export default function useRecentActivity(limit: number, enabled: boolean): UseQueryResult<Page<ActivityLog>> {
  const params = { per_page: limit };
  return useQuery({
    queryKey: queryKeys.activities.recent(limit),
    queryFn: ({ signal }) => activitiesApi.getAll(params, { signal }),
    enabled,
    // A feed: keep it fresh while the dashboard is open
    refetchInterval: 60_000,
  });
}
