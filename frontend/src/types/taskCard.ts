/**
 * Types of the task card's panels (Bitrix24 phase 4): checklist, time log,
 * files, results and chat. Re-exported from api.ts.
 */
import type { User } from './api';

/** A checklist (parent_id null) or one of its items (Bitrix24 step 4.3). */
export interface ChecklistItem {
  id: number;
  parent_id: number | null;
  title: string;
  is_complete: boolean;
  sort_index: number;
}

/** A task's checklists, as GET /tasks/{id}/checklist and every checklist write answer. */
export interface Checklist {
  items: ChecklistItem[];
  /** Bitrix24 couldn't be reached: this is our last copy. */
  stale: boolean;
  can_edit: boolean;
}

/** One entry of a task's time log (Bitrix24 step 4.4). */
export interface TimeEntry {
  id: number;
  user: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
  seconds: number;
  comment: string | null;
  created_date: string | null;
  /** Own entries only (administrators: all). */
  can_edit: boolean;
}

/** A task's time log with its totals and the caller's timer, as every /time and /timer call answers. */
export interface TimeLog {
  entries: TimeEntry[];
  /** Bitrix24 couldn't be reached: this is our last copy. */
  stale: boolean;
  allow_time_tracking: boolean;
  time_estimate: number;
  time_spent: number;
  can_log: boolean;
  /** The caller's running timer, on this task or another. */
  timer: { task_id: number; task_title: string | null; started_at: string } | null;
  /** Set when starting this timer stopped (and logged) one on another task. */
  stopped_task: { id: number; title: string } | null;
}

/** A file attached to a task (Bitrix24 step 4.5). */
export interface TaskFile {
  id: number;
  name: string;
  size: number;
  mime: string | null;
  uploader: { id: number; name: string } | null;
  created_at: string | null;
}

/** A task's files, as GET /tasks/{id}/files and every file write answer. */
export interface TaskFiles {
  files: TaskFile[];
  /** Bitrix24 couldn't be reached: this is our last copy. */
  stale: boolean;
  can_manage: boolean;
  /** Largest upload, in kilobytes. */
  max_kb: number;
}

/** An official result of a task (Bitrix24 step 4.6). */
export interface TaskResult {
  id: number;
  text: string;
  /** Bitrix: open, or closed once the task is done. */
  status: string;
  author: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
  created_at: string | null;
  updated_at: string | null;
  /** Only its author changes it (administrators: all). */
  can_edit: boolean;
}

/** A task's results, as GET /tasks/{id}/results and every result write answer. */
export interface TaskResults {
  results: TaskResult[];
  /** Bitrix24 couldn't be reached: this is our last copy. */
  stale: boolean;
  can_add: boolean;
}

/** A file sent in a task's chat in Bitrix24; id is its Bitrix Drive file ID. */
export interface TaskChatFile {
  id: number;
  name: string;
  size: number;
  type: string;
}

/** A message in a task's chat (Bitrix24 step 4.7; replaced comments). Text may carry Bitrix BBCode. */
export interface TaskChatMessage {
  id: number;
  text: string;
  /** Bitrix's own line ("status changed", "file added"): no author, never edited. */
  is_system: boolean;
  author: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
  files: TaskChatFile[];
  created_at: string | null;
  updated_at: string | null;
  /** Only its author changes it. */
  can_edit: boolean;
}

/** A task's chat (newest messages, oldest first), as GET /tasks/{id}/chat and every chat write answer. */
export interface TaskChat {
  messages: TaskChatMessage[];
  /** Bitrix24 couldn't be reached: this is our last copy. */
  stale: boolean;
  can_post: boolean;
}
