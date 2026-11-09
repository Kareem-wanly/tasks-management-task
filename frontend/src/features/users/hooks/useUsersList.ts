import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import usersApi from '@/api/usersApi';
import { queryKeys } from '@/api/queryKeys';
import useDebounce from '@/hooks/useDebounce';

/** Users list with search, role filter and page, all kept in the URL. */
export default function useUsersList() {
  const [searchParams, setSearchParams] = useSearchParams();

  const [searchTerm, setSearchTermState] = useState(searchParams.get('search') ?? '');
  const [selectedRole, setSelectedRoleState] = useState(searchParams.get('role') ?? '');
  const [page, setPage] = useState(Number(searchParams.get('page')) || 1);
  const debouncedSearch = useDebounce(searchTerm.trim(), 400);

  // Mirror the filters into the URL so a reload or shared link shows the same list
  useEffect(() => {
    const params: Record<string, string> = {};
    if (debouncedSearch) params.search = debouncedSearch;
    if (selectedRole) params.role = selectedRole;
    if (page > 1) params.page = String(page);
    setSearchParams(params, { replace: true });
  }, [debouncedSearch, selectedRole, page, setSearchParams]);

  const params = { search: debouncedSearch, role: selectedRole, page };
  const query = useQuery({
    queryKey: queryKeys.users.list(params),
    queryFn: ({ signal }) => usersApi.getAll(params, { signal }),
    // Keep showing the current page while the next one loads
    placeholderData: keepPreviousData,
  });

  return {
    query,
    users: query.data?.items ?? [],
    lastPage: query.data?.lastPage ?? 1,
    total: query.data?.total ?? 0,
    searchTerm,
    selectedRole,
    page,
    setPage,
    setSearchTerm: (value: string) => {
      setSearchTermState(value);
      setPage(1);
    },
    setSelectedRole: (value: string) => {
      setSelectedRoleState(value);
      setPage(1);
    },
  };
}
