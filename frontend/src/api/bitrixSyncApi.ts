import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { BitrixSyncMode, BitrixSyncStatus, DataBody } from '@/types/api';

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
};

export default bitrixSyncApi;
