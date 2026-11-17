import type { TaskLookups } from '@/hooks/queries/useTaskLookups';
import type { TaskHistoryEntry } from '@/types/api';
import { formatDateTime, formatDuration } from '@/utils/format';

type Labels = Pick<TaskLookups, 'statusLabel' | 'priorityLabel'>;

/** "a, b" → ['a', 'b']; empty → []. */
const names = (value: string | null): string[] => (value ? value.split(', ').filter(Boolean) : []);

const ids = (value: string | null): string[] => (value ? value.split(',').filter(Boolean) : []);

const quote = (value: string | null): string => `"${value ?? ''}"`;

/** Who joined and who left a list of people, e.g. "added Sara as an accomplice". */
function peopleChange(entry: TaskHistoryEntry, role: string): string {
  const before = names(entry.from);
  const after = names(entry.to);
  const added = after.filter((n) => !before.includes(n));
  const removed = before.filter((n) => !after.includes(n));
  const parts = [
    added.length > 0 ? `added ${added.join(', ')} as ${role}` : '',
    removed.length > 0 ? `removed ${removed.join(', ')} from ${role}s` : '',
  ].filter(Boolean);

  return parts.length > 0 ? parts.join(' and ') : `changed the ${role}s`;
}

/** How many files were attached or removed, from the attachment IDs before and after. */
function filesChange(entry: TaskHistoryEntry): string {
  const before = ids(entry.from);
  const after = ids(entry.to);
  const added = after.filter((id) => !before.includes(id)).length;
  const removed = before.filter((id) => !after.includes(id)).length;
  const count = (n: number) => (n === 1 ? 'a file' : `${n} files`);

  if (added > 0 && removed === 0) return `attached ${count(added)}`;
  if (removed > 0 && added === 0) return `removed ${count(removed)}`;
  return 'changed the files';
}

function change(what: string, from: string | null, to: string | null): string {
  if (from && to) return `changed the ${what} from ${from} to ${to}`;
  if (to) return `set the ${what} to ${to}`;
  return `removed the ${what}`;
}

const seconds = (value: string | null): string | null => (value ? formatDuration(Number(value)) : null);

const date = (value: string | null): string | null => (value ? formatDateTime(value) : null);

/** What the person did, as a sentence that follows their name ("Rama changed the status from …"). */
export function describeHistoryEntry(entry: TaskHistoryEntry, labels: Labels): string {
  const { from, to } = entry;

  switch (entry.field) {
    case 'NEW':
      return 'created the task';
    case 'TITLE':
      return `renamed the task from ${quote(from)} to ${quote(to)}`;
    case 'DESCRIPTION':
      return 'edited the description';
    case 'STATUS':
      return change('status', from && labels.statusLabel(from), to && labels.statusLabel(to));
    case 'PRIORITY':
      return change('priority', from && labels.priorityLabel(from), to && labels.priorityLabel(to));
    case 'DEADLINE':
      return change('deadline', date(from), date(to));
    case 'RESPONSIBLE_ID':
      return change('assignee', from, to);
    case 'ACCOMPLICES':
      return peopleChange(entry, 'accomplice');
    case 'AUDITORS':
      return peopleChange(entry, 'auditor');
    case 'CHECKLIST_ITEM_CREATE':
      return `added ${quote(to)} to the checklist`;
    case 'CHECKLIST_ITEM_RENAME':
      return `renamed checklist item ${quote(from)} to ${quote(to)}`;
    case 'CHECKLIST_ITEM_CHECK':
      return `checked ${quote(to)}`;
    case 'CHECKLIST_ITEM_UNCHECK':
      return `unchecked ${quote(to)}`;
    case 'CHECKLIST_ITEM_REMOVE':
      return `removed ${quote(from)} from the checklist`;
    case 'TIME_ESTIMATE':
      return change('time estimate', seconds(from), seconds(to));
    case 'TIME_SPENT_IN_LOGS':
      return `logged time (total ${seconds(to) ?? formatDuration(0)})`;
    case 'UF_TASK_WEBDAV_FILES':
      return filesChange(entry);
    case 'RESULT':
      return 'added a result';
    case 'RESULT_EDIT':
      return 'edited a result';
    case 'RESULT_REMOVE':
      return 'removed a result';
    default:
      return `changed ${entry.field.toLowerCase().replace(/_/g, ' ')}`;
  }
}
