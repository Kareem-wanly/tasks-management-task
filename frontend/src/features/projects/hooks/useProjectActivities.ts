import { useState } from 'react';
import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';
import activitiesApi from '@/api/activitiesApi';
import { queryKeys } from '@/api/queryKeys';
import type { ActivityLog, Page } from '@/types/api';

export interface ProjectActivities {
  query: UseQueryResult<Page<ActivityLog>>;
  page: number;
  setPage: (page: number) => void;
}

/** The project's activity log, one page at a time; only loads once the tab is opened. */
export default function useProjectActivities(projectId: number | string, enabled: boolean): ProjectActivities {
  const [page, setPage] = useState(1);
  const query = useQuery({
    queryKey: queryKeys.projects.activities(projectId, page),
    queryFn: ({ signal }) => activitiesApi.getByProject(projectId, { page }, { signal }),
    placeholderData: keepPreviousData,
    enabled,
  });
  return { query, page, setPage };
}
