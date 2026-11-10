import type { ChartPoint } from '@/components/data/AreaChart';
import type { Project, Task, TaskPriority, TaskStatus } from '@/types/api';
import { daysUntil } from '@/utils/dates';
import { uiLocale } from '@/utils/format';

export interface TaskCounts {
  byStatus: Map<TaskStatus, number>;
  byPriority: Map<TaskPriority, number>;
}

export function countTasks(tasks: Task[]): TaskCounts {
  const byStatus = new Map<TaskStatus, number>();
  const byPriority = new Map<TaskPriority, number>();
  for (const t of tasks) {
    byStatus.set(t.status, (byStatus.get(t.status) ?? 0) + 1);
    byPriority.set(t.priority, (byPriority.get(t.priority) ?? 0) + 1);
  }
  return { byStatus, byPriority };
}

function dayKey(date: Date): string {
  return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

/** Tasks created per local day over the last `days` days, oldest first. */
export function dailySeries(tasks: Task[], days = 30): ChartPoint[] {
  const counts = new Map<string, number>();
  for (const task of tasks) {
    if (!task.created_at) continue;
    const created = new Date(task.created_at);
    if (Number.isNaN(created.getTime())) continue;
    const key = dayKey(created);
    counts.set(key, (counts.get(key) ?? 0) + 1);
  }

  const today = new Date();
  const points: ChartPoint[] = [];
  for (let offset = days - 1; offset >= 0; offset -= 1) {
    const day = new Date(today.getFullYear(), today.getMonth(), today.getDate() - offset);
    points.push({
      label: day.toLocaleDateString(uiLocale(), { month: 'short', day: 'numeric' }),
      value: counts.get(dayKey(day)) ?? 0,
    });
  }
  return points;
}

/** Sort key for "soonest first" with undated items last. */
const dueRank = (dueDate: string | null | undefined): number => daysUntil(dueDate) ?? Number.MAX_SAFE_INTEGER;

/** The user's unfinished tasks, soonest due first (undated last). */
export function openTasksByDue(tasks: Task[], isFinal: (status: TaskStatus) => boolean): Task[] {
  return tasks.filter((t) => !isFinal(t.status)).sort((a, b) => dueRank(a.due_date) - dueRank(b.due_date));
}

/** Tasks due within `days` days (overdue ones are counted separately). */
export function dueWithin(tasks: Task[], days: number): number {
  return tasks.filter((t) => {
    const d = daysUntil(t.due_date);
    return d !== null && d >= 0 && d <= days;
  }).length;
}

/** Open projects first, soonest deadline first; finished and archived ones last. */
export function projectsByUrgency(projects: Project[]): Project[] {
  const closed = (p: Project) => (p.status === 'completed' || p.status === 'archived' ? 1 : 0);
  return [...projects].sort((a, b) => closed(a) - closed(b) || dueRank(a.due_date) - dueRank(b.due_date));
}

export function greeting(name: string | null | undefined, now: Date = new Date()): string {
  const hour = now.getHours();
  const part = hour < 12 ? 'morning' : hour < 18 ? 'afternoon' : 'evening';
  const firstName = name?.trim().split(/\s+/)[0];
  return firstName ? `Good ${part}, ${firstName}` : `Good ${part}`;
}
