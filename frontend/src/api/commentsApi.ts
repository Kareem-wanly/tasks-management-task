import apiClient from '@/lib/apiClient';
import { toPage } from '@/lib/paginate';
import type { Comment, DataBody, PaginatedBody } from '@/types/api';

const commentsApi = {
  getByTask: async (taskId: number) => {
    return toPage(await apiClient.get<PaginatedBody<Comment>>(`/tasks/${taskId}/comments`));
  },

  create: async (taskId: number, commentData: { body: string }) => {
    const res = await apiClient.post<DataBody<Comment>>(`/tasks/${taskId}/comments`, commentData);
    return res.data;
  },

  update: async (commentId: number, commentData: { body: string }) => {
    const res = await apiClient.put<DataBody<Comment>>(`/comments/${commentId}`, commentData);
    return res.data;
  },

  delete: async (commentId: number) => {
    return await apiClient.delete(`/comments/${commentId}`);
  },
};

export default commentsApi;
