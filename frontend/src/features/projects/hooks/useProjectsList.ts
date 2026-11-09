import { useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { keepPreviousData, useQuery, type UseQueryResult } from '@tanstack/react-query';
import projectsApi from '@/api/projectsApi';
import { queryKeys } from '@/api/queryKeys';
import type { Page, Project } from '@/types/api';

export interface ProjectsFiltersState {
  currentSearch: string;
  currentStatus: string;
  currentMember: string;
  currentSort: string;
  currentDirection: string;
  currentPage: number;
}

interface ProjectsList {
  filters: ProjectsFiltersState;
  query: UseQueryResult<Page<Project>>;
  updateFilter: (key: string, value: string) => void;
  handleSortChange: (sortBy: string) => void;
}

/** URL-driven filters and the matching page of projects. */
export default function useProjectsList(): ProjectsList {
  const [searchParams, setSearchParams] = useSearchParams();

  const filters: ProjectsFiltersState = {
    currentSearch: searchParams.get('search') || '',
    currentStatus: searchParams.get('status') || '',
    currentMember: searchParams.get('member_id') || '',
    currentSort: searchParams.get('sort') || 'created_at',
    currentDirection: searchParams.get('direction') || 'desc',
    currentPage: parseInt(searchParams.get('page') || '1', 10) || 1,
  };

  const updateFilter = useCallback(
    (key: string, value: string) => {
      setSearchParams((prev) => {
        const next = new URLSearchParams(prev);
        if (value) next.set(key, value);
        else next.delete(key);
        if (key !== 'page') next.set('page', '1');
        return next;
      });
    },
    [setSearchParams]
  );

  const handleSortChange = (sortBy: string) => {
    const direction = filters.currentSort === sortBy && filters.currentDirection === 'asc' ? 'desc' : 'asc';
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      next.set('sort', sortBy);
      next.set('direction', direction);
      next.set('page', '1');
      return next;
    });
  };

  const params = {
    page: filters.currentPage,
    per_page: 10,
    sort: filters.currentSort,
    direction: filters.currentDirection,
    search: filters.currentSearch,
    status: filters.currentStatus,
    member_id: filters.currentMember,
  };

  const query = useQuery({
    queryKey: queryKeys.projects.list(params),
    queryFn: ({ signal }) => projectsApi.getAll(params, { signal }),
    placeholderData: keepPreviousData,
  });

  return { filters, query, updateFilter, handleSortChange };
}
