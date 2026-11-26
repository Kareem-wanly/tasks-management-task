import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { BitrixSyncFailure, BitrixSyncMode, BitrixSyncStatus, DataBody } from '@/types/api';

/** Bitrix24 step 5.1: the sync status page. */
const bitrixSyncApi = {
  getStatus: async (options: RequestOptions = {}): Promise<BitrixSyncStatus> => {
    const res = await apiClient.get<DataBody<BitrixSyncStatus>>('/bitrix/sync', options);
    return res.data;
  },

  /** Queues a sync on the server; answers the status right after. 409 when one already runs. */
  start: async (mode: BitrixSyncMode): Promise<BitrixSyncStatus> => {
    const res = await apiClient.post<DataBody<BitrixSyncStatus>>('/bitrix/sync', { mode });
    return res.data;
  },

  /** Step 5.2: the open dead letters, most recent first. */
  getFailures: async (options: RequestOptions = {}): Promise<BitrixSyncFailure[]> => {
    const res = await apiClient.get<DataBody<BitrixSyncFailure[]>>('/bitrix/sync/failures', options);
    return res.data;
  },

  /** Queues the failed work again. 409 when it is closed or a retry is already queued. */
  retryFailure: async (id: number): Promise<BitrixSyncFailure> => {
    const res = await apiClient.post<DataBody<BitrixSyncFailure>>(`/bitrix/sync/failures/${id}/retry`, {});
    return res.data;
  },

  dismissFailure: async (id: number): Promise<void> => {
    await apiClient.delete<DataBody<null>>(`/bitrix/sync/failures/${id}`);
  },
};

export default bitrixSyncApi;
