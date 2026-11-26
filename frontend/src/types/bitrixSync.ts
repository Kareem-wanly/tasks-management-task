/** Response shapes of the Bitrix24 sync status page (/bitrix/sync), steps 5.1–5.3. */

/** Bitrix24 step 5.1: where one imported entity stands. `stalled` = marked running long after the time limit. */
export type BitrixSyncState = 'never' | 'running' | 'completed' | 'failed' | 'stalled';

export type BitrixSyncMode = 'changes' | 'full';

export interface BitrixSyncEntity {
  /** users, projects, tasks, checklists, time, files, results, chat, or tasks-changes (the 5-minute sync). */
  entity: string;
  state: BitrixSyncState;
  processed: number;
  total: number;
  error: string | null;
  started_at: string | null;
  last_synced_at: string | null;
}

export interface BitrixSyncStatus {
  is_running: boolean;
  entities: BitrixSyncEntity[];
  /** Open dead letters: background work that failed after all its tries (step 5.2). */
  failures: number;
  /** Our changes Bitrix answered with something else, last 24 hours. */
  conflicts: number;
  /** Our changes Bitrix never echoed back, last 24 hours. */
  unconfirmed: number;
  /** Step 5.3: the nightly comparison of users, projects and tasks. */
  reconciliation: BitrixReconciliation[];
}

/** Bitrix24 step 5.2: which background work failed. */
export type BitrixSyncOperation = 'task' | 'project' | 'task_chat' | 'import';

/** Only open failures are listed: `retrying` = queued again, closes when the work next succeeds. */
export type BitrixSyncFailureStatus = 'pending' | 'retrying';

export interface BitrixSyncFailure {
  id: number;
  operation: BitrixSyncOperation;
  /** The Bitrix ID of the task or group, or the import entity (users, tasks, tasks-changes, ...). */
  subject: string;
  error: string;
  attempts: number;
  status: BitrixSyncFailureStatus;
  last_failed_at: string;
  retried_at: string | null;
  /** False only while a retry waits for the worker; true again once it goes stale. */
  can_retry: boolean;
}

/** Step 5.3: one nightly comparison of the Bitrix24 IDs with ours. */
export interface BitrixReconciliationCheck {
  checked_at: string;
  bitrix_count: number;
  local_count: number;
  /** In Bitrix but not here (skipped by the import). */
  missing_here_count: number;
  /** Here but not in Bitrix (not deleted). */
  extra_here_count: number;
  /** The first Bitrix IDs of each side (at most 20). */
  missing_here: number[];
  extra_here: number[];
}

/** One day: every check matched, one found a difference, or nothing was compared (breaks a streak). */
export type ReconciliationNightState = 'clean' | 'differences' | 'none';

export interface BitrixReconciliation {
  /** users, projects or tasks. */
  entity: string;
  /** Days in a row, up to today (or yesterday while today is unchecked), whose checks all matched. */
  clean_nights: number;
  /** Null until the entity's first full import. */
  latest: BitrixReconciliationCheck | null;
  /** The last 7 days, oldest first, ending today. */
  nights: { date: string; state: ReconciliationNightState }[];
}
