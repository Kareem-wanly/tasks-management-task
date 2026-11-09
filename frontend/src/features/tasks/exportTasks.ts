import tasksApi from '@/api/tasksApi';
import { TASK_PRIORITY_LABELS, TASK_STATUS_LABELS } from '@/constants/statuses';
import { downloadText } from '@/lib/download';
import type { QueryParams } from '@/lib/paginate';
import type { Task } from '@/types/api';
import { toCsv } from '@/utils/csv';
import { daysFromNow, toDateOnly } from '@/utils/dates';

/** 20 pages of 100 (the API's page cap) is plenty for a spreadsheet and bounds the request count. */
const MAX_PAGES = 20;

const HEADER = ['ID', 'Title', 'Project', 'Status', 'Priority', 'Assignee', 'Due date', 'Overdue', 'Created', 'Completed'];

function toRow(task: Task): (string | number)[] {
  return [
    task.id,
    task.title,
    task.project?.title ?? '',
    TASK_STATUS_LABELS[task.status],
    TASK_PRIORITY_LABELS[task.priority],
    task.assignee?.name ?? '',
    toDateOnly(task.due_date) ?? '',
    task.is_overdue ? 'Yes' : 'No',
    toDateOnly(task.created_at) ?? '',
    toDateOnly(task.completed_at) ?? '',
  ];
}

/** Downloads every task matching `params` as CSV and returns how many were exported. */
export async function exportTasksCsv(params: QueryParams): Promise<number> {
  const tasks: Task[] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const result = await tasksApi.getAll({ ...params, page, per_page: 100 });
    tasks.push(...result.items);
    lastPage = result.lastPage;
    page += 1;
  } while (page <= lastPage && page <= MAX_PAGES);

  downloadText(`tasks-${daysFromNow(0)}.csv`, toCsv([HEADER, ...tasks.map(toRow)]));
  return tasks.length;
}
