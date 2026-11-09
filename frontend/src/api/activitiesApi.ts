import apiClient, { type RequestOptions } from '@/lib/apiClient';
import { toPage, toQuery, type QueryParams } from '@/lib/paginate';
import type { ActivityLog, PaginatedBody } from '@/types/api';

const activitiesApi = {
  getAll: async (params: QueryParams = {}, options: RequestOptions = {}) => {
    return toPage(await apiClient.get<PaginatedBody<ActivityLog>>(`/activities${toQuery(params)}`, options));
  },

  getByProject: async (projectId: number | string, params: QueryParams = {}, options: RequestOptions = {}) => {
    return toPage(
      await apiClient.get<PaginatedBody<ActivityLog>>(`/projects/${projectId}/activities${toQuery(params)}`, options)
    );
  },
};

export default activitiesApi;
