import apiClient, { type RequestOptions } from '@/lib/apiClient';
import type { TimeEntry, TimeLog } from '@/types/api';

interface TimeLogBody {
  data: TimeEntry[];
  meta: Omit<TimeLog, 'entries'>;
}

export interface TimeEntryInput {
  hours: number;
  minutes: number;
  comment: string | null;
}

const toLog = (body: TimeLogBody): TimeLog => ({ entries: body.data, ...body.meta });

/** A task's time log and the caller's timer (Bitrix24 step 4.4). Every call answers with the whole log. */
const timeApi = {
  /** Fetched from Bitrix24 on each call: it sends no events for the time log. */
  get: async (taskId: number, options: RequestOptions = {}) =>
    toLog(await apiClient.get<TimeLogBody>(`/tasks/${taskId}/time`, options)),

  add: async (taskId: number, entry: TimeEntryInput) =>
    toLog(await apiClient.post<TimeLogBody>(`/tasks/${taskId}/time`, { ...entry })),

  update: async (entryId: number, entry: TimeEntryInput) =>
    toLog(await apiClient.put<TimeLogBody>(`/time-entries/${entryId}`, { ...entry })),

  remove: async (entryId: number) => toLog(await apiClient.delete<TimeLogBody>(`/time-entries/${entryId}`)),

  /** A timer running on another task is stopped and its time logged. */
  startTimer: async (taskId: number) => toLog(await apiClient.post<TimeLogBody>(`/tasks/${taskId}/timer`, {})),

  /** Logs the time since the start. */
  stopTimer: async (taskId: number, comment: string | null) =>
    toLog(await apiClient.post<TimeLogBody>(`/tasks/${taskId}/timer/stop`, { comment })),
};

export default timeApi;
